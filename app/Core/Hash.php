<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Password hashing (argon2id when available, bcrypt fallback) and
 * constant-time token helpers.
 */
final class Hash
{
    public static function make(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 1,
            ]);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function check(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_needs_rehash($hash, $algo);
    }

    /** Random URL-safe token. */
    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /** Numeric OTP of given length. */
    public static function otp(int $digits = 6): string
    {
        $otp = '';
        for ($i = 0; $i < $digits; $i++) {
            $otp .= (string) random_int(0, 9);
        }
        return $otp;
    }

    /** Constant-time comparison. */
    public static function equals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /** HMAC-SHA256 hex digest. */
    public static function hmac(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }
}
