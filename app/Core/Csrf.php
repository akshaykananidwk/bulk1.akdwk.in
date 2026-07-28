<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token management. One token per session, rotated on login.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = Hash::token(32);
        }
        return (string) $_SESSION[self::KEY];
    }

    public static function rotate(): void
    {
        $_SESSION[self::KEY] = Hash::token(32);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    /**
     * Verify a request token (form field `_token` or X-CSRF-Token header).
     */
    public static function verify(Request $request): bool
    {
        $token = (string) ($request->input('_token') ?? $request->header('X-CSRF-Token') ?? '');
        if ($token === '' || empty($_SESSION[self::KEY])) {
            return false;
        }
        return hash_equals((string) $_SESSION[self::KEY], $token);
    }
}
