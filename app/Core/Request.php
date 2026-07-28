<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP request wrapper. All superglobal access flows through this class.
 */
final class Request
{
    private static ?self $instance = null;

    private array $get;
    private array $post;
    private array $server;
    private array $cookies;
    private array $files;
    private ?array $jsonCache = null;
    private string $rawBody = '';
    private array $routeParams = [];

    private function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->cookies = $_COOKIE;
        $this->files = $_FILES;
        if (PHP_SAPI !== 'cli') {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }
    }

    public static function capture(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function instance(): self
    {
        return self::capture();
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST') {
            $spoofed = strtoupper((string) ($this->post['_method'] ?? ''));
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }
        return $method;
    }

    public function path(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    public function fullUrl(): string
    {
        $scheme = self::isSecure() ? 'https' : 'http';
        return $scheme . '://' . $this->host() . ($this->server['REQUEST_URI'] ?? '/');
    }

    public function host(): string
    {
        $host = (string) ($this->server['HTTP_HOST'] ?? $this->server['SERVER_NAME'] ?? 'localhost');
        // Strip port
        return strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
    }

    public static function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        return ((int) ($_SERVER['SERVER_PORT'] ?? 80)) === 443;
    }

    public function ip(): string
    {
        // Only trust proxy headers when behind a configured trusted proxy.
        $trusted = (array) Config::get('app.trusted_proxies', []);
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if ($trusted && in_array($remote, $trusted, true)) {
            $forwarded = (string) ($this->server['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($forwarded !== '') {
                $parts = array_map('trim', explode(',', $forwarded));
                $candidate = $parts[0] ?? '';
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
        return $remote;
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return (string) $this->server[$key];
        }
        // Content-Type / Content-Length are not HTTP_ prefixed
        $special = strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$special]) ? (string) $this->server[$special] : $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization', '');
        if ($auth && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Query string parameter */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /** POST body / JSON body / query — in that order */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        $json = $this->json();
        if (is_array($json) && array_key_exists($key, $json)) {
            return $json[$key];
        }
        return $this->get[$key] ?? $default;
    }

    /** Trimmed string input */
    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->input($key);
        if ($value === null) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function arr(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [];
    }

    /**
     * Merge values into the request input (highest precedence).
     * Used by controllers that normalise input before validation.
     */
    public function merge(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->post[$key] = $value;
        }
    }

    /** All input merged (POST + JSON + GET) */
    public function all(): array
    {
        $json = $this->json() ?? [];
        return array_merge($this->get, is_array($json) ? $json : [], $this->post);
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function json(): ?array
    {
        if ($this->jsonCache !== null) {
            return $this->jsonCache;
        }
        $contentType = (string) ($this->server['CONTENT_TYPE'] ?? '');
        if ($this->rawBody !== '' && str_contains($contentType, 'json')) {
            $decoded = json_decode($this->rawBody, true);
            if (is_array($decoded)) {
                $this->jsonCache = $decoded;
                return $decoded;
            }
        }
        return null;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return isset($this->cookies[$key]) ? (string) $this->cookies[$key] : $default;
    }

    public function isAjax(): bool
    {
        return strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        if (str_starts_with($this->path(), '/api/')) {
            return true;
        }
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');
        return $this->isAjax() || str_contains($accept, 'application/json');
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function routeParams(): array
    {
        return $this->routeParams;
    }
}
