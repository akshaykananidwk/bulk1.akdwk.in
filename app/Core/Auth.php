<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Authentication: sessions, remember-me, 2FA state, impersonation.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $resolved = false;

    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
        if ($userId > 0) {
            $user = DB::table('users')->where('id', $userId)->where('status', 'active')->first();
            if ($user !== null) {
                self::$user = $user;
                return $user;
            }
            self::logoutSessionOnly();
        }

        // Remember-me cookie: "selector:validator"
        $cookie = Request::instance()->cookie('kwc_remember');
        if ($cookie !== null && str_contains($cookie, ':')) {
            [$selector, $validator] = explode(':', $cookie, 2);
            $row = DB::table('user_sessions')
                ->where('selector', $selector)
                ->where('type', 'remember')
                ->where('expires_at', '>', date('Y-m-d H:i:s'))
                ->first();
            if ($row !== null && hash_equals($row['token_hash'], hash('sha256', $validator))) {
                $user = DB::table('users')->where('id', $row['user_id'])->where('status', 'active')->first();
                if ($user !== null) {
                    self::establishSession($user, false);
                    self::$user = $user;
                    return $user;
                }
            }
        }

        return null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Attempt email+password login. Returns user row or null.
     * Handles throttling and login attempt logging.
     */
    public static function attempt(string $email, string $password, bool $remember, string $ip): ?array
    {
        $throttleKey = 'login:' . sha1(strtolower($email) . '|' . $ip);
        if (RateLimiter::tooManyAttempts($throttleKey, 5, 900)) {
            self::logAttempt($email, $ip, false, 'throttled');
            return null;
        }

        $user = DB::table('users')->where('email', strtolower($email))->first();
        if ($user === null || !Hash::check($password, (string) $user['password'])) {
            self::logAttempt($email, $ip, false, 'invalid_credentials');
            return null;
        }
        if ($user['status'] !== 'active') {
            self::logAttempt($email, $ip, false, 'inactive');
            return null;
        }

        RateLimiter::clear($throttleKey);

        if (Hash::needsRehash((string) $user['password'])) {
            DB::table('users')->where('id', $user['id'])->update(['password' => Hash::make($password)]);
        }

        // 2FA gate: caller must redirect to /2fa when this returns pending
        if (!empty($user['two_factor_secret']) || !empty($user['two_factor_whatsapp'])) {
            $_SESSION['2fa_pending_user_id'] = (int) $user['id'];
            $_SESSION['2fa_remember'] = $remember;
            self::logAttempt($email, $ip, true, '2fa_pending');
            return ['__2fa_required' => true] + $user;
        }

        self::login($user, $remember);
        self::logAttempt($email, $ip, true, 'success');
        return $user;
    }

    /**
     * Complete login for a verified user.
     */
    public static function login(array $user, bool $remember = false): void
    {
        session_regenerate_id(true);
        Csrf::rotate();
        $_SESSION['auth_user_id'] = (int) $user['id'];
        unset($_SESSION['2fa_pending_user_id'], $_SESSION['2fa_remember']);
        self::$user = $user;
        self::$resolved = true;

        DB::table('users')->where('id', $user['id'])->update([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => Request::instance()->ip(),
        ]);

        self::establishSession($user, $remember);
    }

    private static function establishSession(array $user, bool $remember): void
    {
        $_SESSION['auth_user_id'] = (int) $user['id'];

        if ($remember) {
            $selector = Hash::token(12);
            $validator = Hash::token(32);
            DB::table('user_sessions')->insert([
                'user_id' => (int) $user['id'],
                'selector' => $selector,
                'token_hash' => hash('sha256', $validator),
                'type' => 'remember',
                'ip' => Request::instance()->ip(),
                'user_agent' => mb_substr(Request::instance()->userAgent(), 0, 255),
                'expires_at' => date('Y-m-d H:i:s', time() + 60 * 86400),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            setcookie('kwc_remember', $selector . ':' . $validator, [
                'expires' => time() + 60 * 86400,
                'path' => '/',
                'secure' => Request::isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function logout(): void
    {
        $cookie = Request::instance()->cookie('kwc_remember');
        if ($cookie !== null && str_contains($cookie, ':')) {
            [$selector] = explode(':', $cookie, 2);
            DB::table('user_sessions')->where('selector', $selector)->delete();
        }
        setcookie('kwc_remember', '', ['expires' => time() - 3600, 'path' => '/']);
        self::logoutSessionOnly();
    }

    private static function logoutSessionOnly(): void
    {
        unset($_SESSION['auth_user_id'], $_SESSION['impersonator_id'], $_SESSION['2fa_pending_user_id']);
        self::$user = null;
        self::$resolved = true;
        session_regenerate_id(true);
    }

    // -- Roles & permissions --------------------------------------------------

    public static function isSuperAdmin(): bool
    {
        $user = self::user();
        return $user !== null && ($user['is_super_admin'] ?? 0);
    }

    /**
     * Check a permission slug against the user's role + direct grants.
     */
    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        if (self::isSuperAdmin()) {
            return true;
        }

        static $cache = [];
        $userId = (int) $user['id'];
        if (!isset($cache[$userId])) {
            $slugs = [];
            if (!empty($user['role_id'])) {
                $rows = DB::table('role_permissions')
                    ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                    ->where('role_permissions.role_id', (int) $user['role_id'])
                    ->select('permissions.slug')
                    ->get();
                foreach ($rows as $row) {
                    $slugs[$row['slug']] = true;
                }
            }
            $direct = DB::table('user_permissions')
                ->join('permissions', 'user_permissions.permission_id', '=', 'permissions.id')
                ->where('user_permissions.user_id', $userId)
                ->select('permissions.slug')
                ->get();
            foreach ($direct as $row) {
                $slugs[$row['slug']] = true;
            }
            $cache[$userId] = $slugs;
        }

        // Wildcard: "contacts.*" grants "contacts.view"
        if (isset($cache[$userId][$permission])) {
            return true;
        }
        $prefix = explode('.', $permission)[0] ?? '';
        return isset($cache[$userId][$prefix . '.*']);
    }

    // -- Impersonation --------------------------------------------------------

    public static function impersonate(int $targetUserId): bool
    {
        if (!self::isSuperAdmin()) {
            return false;
        }
        $target = DB::table('users')->where('id', $targetUserId)->first();
        if ($target === null) {
            return false;
        }
        $_SESSION['impersonator_id'] = self::id();
        $_SESSION['auth_user_id'] = $targetUserId;
        self::$user = $target;
        return true;
    }

    public static function stopImpersonating(): bool
    {
        $originalId = (int) ($_SESSION['impersonator_id'] ?? 0);
        if ($originalId <= 0) {
            return false;
        }
        unset($_SESSION['impersonator_id']);
        $_SESSION['auth_user_id'] = $originalId;
        self::$user = null;
        self::$resolved = false;
        return true;
    }

    public static function isImpersonating(): bool
    {
        return !empty($_SESSION['impersonator_id']);
    }

    private static function logAttempt(string $email, string $ip, bool $success, string $reason): void
    {
        try {
            DB::table('login_attempts')->insert([
                'email' => mb_substr(strtolower($email), 0, 191),
                'ip' => $ip,
                'success' => $success ? 1 : 0,
                'reason' => $reason,
                'user_agent' => mb_substr(Request::instance()->userAgent(), 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // never block auth on logging
        }
    }
}
