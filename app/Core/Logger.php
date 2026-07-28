<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Channel-based file logger with daily rotation and secret redaction.
 * Channels: app, api, webhook, meta, queue, update, error, slow, install.
 */
final class Logger
{
    private static array $instances = [];
    private string $channel;

    private function __construct(string $channel)
    {
        $this->channel = preg_replace('/[^a-z0-9_-]/i', '', $channel) ?: 'app';
    }

    public static function channel(string $channel = 'app'): self
    {
        if (!isset(self::$instances[$channel])) {
            self::$instances[$channel] = new self($channel);
        }
        return self::$instances[$channel];
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return;
        }

        $file = $dir . '/' . $this->channel . '-' . date('Y-m-d') . '.log';
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $level,
            self::redact($message),
            $context ? ' ' . self::redact(json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') : ''
        );
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Redact tokens/keys/passwords from log output.
     */
    public static function redact(string $text): string
    {
        $patterns = [
            // GitHub tokens
            '/gh[pousr]_[A-Za-z0-9_]{20,}/' => '[REDACTED_TOKEN]',
            '/github_pat_[A-Za-z0-9_]{20,}/' => '[REDACTED_TOKEN]',
            // Meta / generic bearer tokens
            '/EAA[A-Za-z0-9]{20,}/' => '[REDACTED_TOKEN]',
            '/Bearer\s+[A-Za-z0-9._\-]{16,}/' => 'Bearer [REDACTED]',
            // Key-value style secrets in JSON or query strings
            '/("?(?:password|passwd|secret|token|api_key|apikey|access_token|client_secret|authorization)"?\s*[:=]\s*")[^"]{4,}(")/i' => '$1[REDACTED]$2',
            '/((?:password|secret|token|api_key|access_token|client_secret)=)[^&\s]{4,}/i' => '$1[REDACTED]',
            // Stripe / Razorpay style keys
            '/sk_(live|test)_[A-Za-z0-9]{10,}/' => '[REDACTED_KEY]',
            '/rzp_(live|test)_[A-Za-z0-9]{6,}/' => '[REDACTED_KEY]',
        ];
        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }
        return $text;
    }
}
