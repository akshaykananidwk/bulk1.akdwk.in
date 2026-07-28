<?php

declare(strict_types=1);

namespace App\Core;

/**
 * AES-256-GCM encryption using APP_KEY. Used for Meta tokens, API secrets,
 * payment keys — anything secret at rest.
 */
final class Crypt
{
    private const CIPHER = 'aes-256-gcm';

    private static function key(): string
    {
        $key = (string) Config::get('app.key', '');
        if ($key === '') {
            throw new \RuntimeException('APP_KEY is not set. Re-run the installer or repair config/config.php.');
        }
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false || strlen($decoded) !== 32) {
                throw new \RuntimeException('APP_KEY is invalid.');
            }
            return $decoded;
        }
        return hash('sha256', $key, true);
    }

    /**
     * Encrypt a string. Output format: base64(iv . tag . ciphertext).
     */
    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt a string produced by encrypt(). Returns null on tamper/failure.
     */
    public static function decrypt(string $payload): ?string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plaintext === false ? null : $plaintext;
    }

    /**
     * Mask a secret for display: ghp_••••••1234
     */
    public static function mask(string $secret, int $visible = 4): string
    {
        $length = mb_strlen($secret);
        if ($length <= $visible) {
            return str_repeat('•', max(6, $length));
        }
        $prefix = '';
        if (preg_match('/^([a-zA-Z0-9]+_)/', $secret, $m) && strlen($m[1]) < $length - $visible) {
            $prefix = $m[1];
        }
        return $prefix . str_repeat('•', 6) . mb_substr($secret, -$visible);
    }
}
