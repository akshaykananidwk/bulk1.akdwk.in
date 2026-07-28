<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pure-PHP SMTP client (sockets, STARTTLS/SSL, AUTH LOGIN/PLAIN).
 * Falls back to PHP mail() when SMTP is not configured.
 */
final class Mail
{
    /** Last send failure reason — shown in the admin UI, never swallowed. */
    public static ?string $lastError = null;

    private array $to = [];
    private string $subject = '';
    private string $htmlBody = '';
    private string $textBody = '';
    private array $customHeaders = [];
    private ?string $fromEmail = null;
    private ?string $fromName = null;
    private ?string $replyTo = null;

    public static function make(): self
    {
        return new self();
    }

    public function to(string $email, string $name = ''): self
    {
        $this->to[] = [$email, $name];
        return $this;
    }

    public function from(string $email, string $name = ''): self
    {
        $this->fromEmail = $email;
        $this->fromName = $name;
        return $this;
    }

    public function replyTo(string $email): self
    {
        $this->replyTo = $email;
        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $html): self
    {
        $this->htmlBody = $html;
        return $this;
    }

    public function text(string $text): self
    {
        $this->textBody = $text;
        return $this;
    }

    /**
     * Render a mail view from resources/mail/ with data.
     */
    public function view(string $template, array $data = []): self
    {
        $file = RESOURCE_PATH . '/mail/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Mail template not found: ' . $template);
        }
        ob_start();
        (static function (string $__file, array $__data) {
            foreach ($__data as $__key => $__value) {
                if (preg_match('/^[a-zA-Z_]\w*$/', (string) $__key)) {
                    ${$__key} = $__value;
                }
            }
            unset($__key, $__value, $__data);
            include $__file;
        })($file, $data);
        $this->htmlBody = (string) ob_get_clean();
        return $this;
    }

    /**
     * Send the message. Returns true on success.
     */
    public function send(): bool
    {
        $config = self::smtpConfig();
        $fromEmail = $this->fromEmail ?? (string) ($config['from_email'] ?? 'noreply@localhost');
        $fromName = $this->fromName ?? (string) ($config['from_name'] ?? (string) setting('app_name', 'Krishna WhatsApp Cloud'));

        if (empty($this->to) || $this->subject === '') {
            return false;
        }

        $boundary = 'kwc_' . bin2hex(random_bytes(12));
        $headers = [
            'MIME-Version' => '1.0',
            'From' => self::encodeAddress($fromEmail, $fromName),
            'Date' => date('r'),
            'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost') . '>',
            'X-Mailer' => 'KrishnaWhatsAppCloud',
        ];
        if ($this->replyTo !== null) {
            $headers['Reply-To'] = $this->replyTo;
        }
        foreach ($this->customHeaders as $name => $value) {
            $headers[$name] = $value;
        }

        $text = $this->textBody !== '' ? $this->textBody : strip_tags($this->htmlBody);
        if ($this->htmlBody !== '') {
            $headers['Content-Type'] = 'multipart/alternative; boundary="' . $boundary . '"';
            $body = "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($text))
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($this->htmlBody))
                . "--{$boundary}--\r\n";
        } else {
            $headers['Content-Type'] = 'text/plain; charset=UTF-8';
            $headers['Content-Transfer-Encoding'] = 'base64';
            $body = chunk_split(base64_encode($text));
        }

        self::$lastError = null;

        if (($config['driver'] ?? 'mail') === 'smtp' && !empty($config['host'])) {
            try {
                $sent = $this->sendSmtp($config, $fromEmail, $headers, $body);
                self::recordResult(true);
                return $sent;
            } catch (\Throwable $e) {
                self::$lastError = 'SMTP: ' . $e->getMessage();
                Logger::channel('mail')->error('SMTP send failed', ['to' => array_map(fn ($t) => $t[0], $this->to), 'error' => $e->getMessage()]);
                self::recordResult(false);
                return false;
            }
        }

        // mail() fallback — most VPS/aaPanel servers have NO local sendmail,
        // so a false here almost always means "configure SMTP".
        $headerString = '';
        foreach ($headers as $name => $value) {
            $headerString .= $name . ': ' . $value . "\r\n";
        }
        $toString = implode(', ', array_map(fn ($t) => self::encodeAddress($t[0], $t[1]), $this->to));
        $sent = @mail($toString, self::encodeHeader($this->subject), $body, $headerString);
        if (!$sent) {
            self::$lastError = __('mail.no_sendmail', "PHP mail() failed — this server has no sendmail configured. Set up SMTP in Admin → Global Settings → Mail (driver: smtp) with your email provider's details.");
            Logger::channel('mail')->error('mail() send failed', ['to' => $toString]);
        }
        self::recordResult($sent);
        return $sent;
    }

    /**
     * Persist the last mail outcome so the admin UI can show WHY email
     * is not going out (settings table may be absent during install).
     */
    private static function recordResult(bool $sent): void
    {
        try {
            if ($sent) {
                set_setting('mail_last_error', '');
                set_setting('mail_last_success_at', now());
            } else {
                set_setting('mail_last_error', (string) self::$lastError);
                set_setting('mail_last_error_at', now());
            }
        } catch (\Throwable) {
            // installer / early-boot context — file log already has it
        }
    }

    private function sendSmtp(array $config, string $fromEmail, array $headers, string $body): bool
    {
        $host = (string) $config['host'];
        $port = (int) ($config['port'] ?? 587);
        $encryption = (string) ($config['encryption'] ?? 'tls'); // tls | ssl | none
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $timeout = 20;

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new \RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($socket, $timeout);

        $read = function () use ($socket): string {
            $data = '';
            while (($line = fgets($socket, 2048)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $expect = function (string $response, array $codes, string $step): void {
            $code = (int) substr($response, 0, 3);
            if (!in_array($code, $codes, true)) {
                throw new \RuntimeException("SMTP {$step} failed: " . trim($response));
            }
        };
        $write = function (string $command) use ($socket): void {
            fwrite($socket, $command . "\r\n");
        };

        $expect($read(), [220], 'greeting');
        $hostname = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost';
        $write('EHLO ' . $hostname);
        $ehlo = $read();
        $expect($ehlo, [250], 'EHLO');

        if ($encryption === 'tls') {
            $write('STARTTLS');
            $expect($read(), [220], 'STARTTLS');
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('TLS negotiation failed');
            }
            $write('EHLO ' . $hostname);
            $expect($read(), [250], 'EHLO(TLS)');
        }

        if ($username !== '') {
            $write('AUTH LOGIN');
            $expect($read(), [334], 'AUTH');
            $write(base64_encode($username));
            $expect($read(), [334], 'AUTH user');
            $write(base64_encode($password));
            $expect($read(), [235], 'AUTH pass');
        }

        $write('MAIL FROM:<' . $fromEmail . '>');
        $expect($read(), [250], 'MAIL FROM');

        foreach ($this->to as [$email]) {
            $write('RCPT TO:<' . $email . '>');
            $expect($read(), [250, 251], 'RCPT TO');
        }

        $write('DATA');
        $expect($read(), [354], 'DATA');

        $data = 'To: ' . implode(', ', array_map(fn ($t) => self::encodeAddress($t[0], $t[1]), $this->to)) . "\r\n";
        $data .= 'Subject: ' . self::encodeHeader($this->subject) . "\r\n";
        foreach ($headers as $name => $value) {
            $data .= $name . ': ' . $value . "\r\n";
        }
        $data .= "\r\n" . preg_replace('/^\./m', '..', $body) . "\r\n.";
        $write($data);
        $expect($read(), [250], 'message body');

        $write('QUIT');
        fclose($socket);
        return true;
    }

    private static function smtpConfig(): array
    {
        // DB settings (admin panel) override config file
        $settings = [];
        try {
            $settings = [
                'driver' => setting('mail_driver'),
                'host' => setting('mail_host'),
                'port' => setting('mail_port'),
                'encryption' => setting('mail_encryption'),
                'username' => setting('mail_username'),
                'password' => setting('mail_password') !== null ? Crypt::decrypt((string) setting('mail_password')) : null,
                'from_email' => setting('mail_from_email'),
                'from_name' => setting('mail_from_name'),
            ];
        } catch (\Throwable) {
            // settings table unavailable (installer context)
        }

        $config = Config::all('mail');
        foreach ($settings as $key => $value) {
            if ($value !== null && $value !== '') {
                $config[$key] = $value;
            }
        }
        return $config;
    }

    private static function encodeAddress(string $email, string $name): string
    {
        if ($name === '') {
            return $email;
        }
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
