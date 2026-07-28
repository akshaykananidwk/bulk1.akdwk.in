<?php

declare(strict_types=1);

namespace App\Core;

/**
 * cURL HTTP client: retries with exponential backoff, timeouts,
 * streaming downloads, JSON helpers, request logging with redaction.
 */
final class Http
{
    private string $baseUrl = '';
    private array $headers = [];
    private int $timeout = 30;
    private int $connectTimeout = 10;
    private int $retries = 0;
    private array $retryDelays = [2, 8, 30, 120];
    private ?string $logChannel = null;

    public static function make(): self
    {
        return new self();
    }

    public function baseUrl(string $url): self
    {
        $this->baseUrl = rtrim($url, '/');
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    public function withToken(string $token): self
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    public function retry(int $times, array $delays = []): self
    {
        $this->retries = $times;
        if ($delays) {
            $this->retryDelays = $delays;
        }
        return $this;
    }

    public function logTo(string $channel): self
    {
        $this->logChannel = $channel;
        return $this;
    }

    public function get(string $url, array $query = []): HttpResponse
    {
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $this->send('GET', $url);
    }

    public function post(string $url, mixed $body = null): HttpResponse
    {
        return $this->send('POST', $url, $body);
    }

    public function postForm(string $url, array $fields): HttpResponse
    {
        return $this->send('POST', $url, $fields, 'form');
    }

    public function postMultipart(string $url, array $fields): HttpResponse
    {
        return $this->send('POST', $url, $fields, 'multipart');
    }

    public function put(string $url, mixed $body = null): HttpResponse
    {
        return $this->send('PUT', $url, $body);
    }

    public function delete(string $url, mixed $body = null): HttpResponse
    {
        return $this->send('DELETE', $url, $body);
    }

    /**
     * Stream a large download straight to a file (never into memory).
     */
    public function download(string $url, string $destination, ?callable $progress = null): HttpResponse
    {
        $fp = fopen($destination, 'wb');
        if ($fp === false) {
            throw new \RuntimeException('Cannot open download destination: ' . $destination);
        }

        $attempt = 0;
        while (true) {
            $ch = $this->buildCurl('GET', $url, null, null);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, max($this->timeout, 600));
            if ($progress !== null) {
                curl_setopt($ch, CURLOPT_NOPROGRESS, false);
                curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($res, $dlTotal, $dlNow) use ($progress) {
                    $progress((int) $dlNow, (int) $dlTotal);
                    return 0;
                });
            }

            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error === '' && $status >= 200 && $status < 300) {
                fclose($fp);
                return new HttpResponse($status, '', []);
            }

            $attempt++;
            if ($attempt > $this->retries) {
                fclose($fp);
                @unlink($destination);
                return new HttpResponse($status ?: 0, '', [], $error !== '' ? $error : 'Download failed with HTTP ' . $status);
            }
            // Reset the file and retry
            ftruncate($fp, 0);
            rewind($fp);
            sleep($this->retryDelays[min($attempt - 1, count($this->retryDelays) - 1)]);
        }
    }

    private function send(string $method, string $url, mixed $body = null, ?string $bodyType = 'json'): HttpResponse
    {
        $attempt = 0;
        $started = microtime(true);

        while (true) {
            $ch = $this->buildCurl($method, $url, $body, $bodyType);
            $responseHeaders = [];
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($res, string $header) use (&$responseHeaders) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($header);
            });

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $response = new HttpResponse($status, is_string($raw) ? $raw : '', $responseHeaders, $error ?: null);

            $shouldRetry = ($error !== '' || $status === 429 || $status >= 500) && $attempt < $this->retries;

            if ($this->logChannel !== null) {
                Logger::channel($this->logChannel)->info(sprintf(
                    '%s %s -> %d (%.0fms)%s',
                    $method,
                    Logger::redact($this->fullUrl($url)),
                    $status,
                    (microtime(true) - $started) * 1000,
                    $error !== '' ? ' curl_error=' . $error : ''
                ));
            }

            if (!$shouldRetry) {
                return $response;
            }

            $attempt++;
            $delay = $this->retryDelays[min($attempt - 1, count($this->retryDelays) - 1)];
            // Honour Retry-After if present
            if (isset($responseHeaders['retry-after']) && is_numeric($responseHeaders['retry-after'])) {
                $delay = max($delay, (int) $responseHeaders['retry-after']);
            }
            sleep(min($delay, 300));
        }
    }

    private function buildCurl(string $method, string $url, mixed $body, ?string $bodyType): \CurlHandle
    {
        $ch = curl_init($this->fullUrl($url));
        if ($ch === false) {
            throw new \RuntimeException('curl_init failed');
        }

        $headers = $this->headers;

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($body !== null && $method !== 'GET') {
            if ($bodyType === 'json') {
                $payload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            } elseif ($bodyType === 'form') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(is_array($body) ? $body : []));
                $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            } elseif ($bodyType === 'multipart') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body); // may contain CURLFile
            }
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        if ($headerLines) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        return $ch;
    }

    private function fullUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return $this->baseUrl . '/' . ltrim($url, '/');
    }
}

/**
 * Immutable HTTP response value object.
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers,
        public readonly ?string $error = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->error === null && $this->status >= 200 && $this->status < 300;
    }

    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
