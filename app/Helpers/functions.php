<?php

declare(strict_types=1);

/**
 * Global helper functions. Kept deliberately small — everything else lives
 * in namespaced classes.
 */

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\DB;
use App\Core\Lang;
use App\Core\Router;
use App\Core\Tenant;

if (!function_exists('e')) {
    /**
     * HTML-escape for output. EVERY dynamic value echoed in a view goes
     * through this (or is produced by a helper that already escapes).
     */
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('url')) {
    /**
     * Absolute URL for a path. url('/tenant/inbox') -> https://app.example.com/tenant/inbox
     */
    function url(string $path = '/'): string
    {
        $base = rtrim((string) Config::get('app.url', ''), '/');
        if ($base === '' && PHP_SAPI !== 'cli') {
            $scheme = \App\Core\Request::isSecure() ? 'https' : 'http';
            $base = $scheme . '://' . \App\Core\Request::instance()->host();
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Versioned asset URL (cache-busted after updates).
     */
    function asset(string $path): string
    {
        $version = (string) setting('assets_version', (string) config('app.version', '1'));
        return url('/assets/' . ltrim($path, '/')) . '?v=' . rawurlencode($version);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        return Router::instance()->url($name, $params);
    }
}

if (!function_exists('__')) {
    /**
     * Translate a language key. __('inbox.title'), __('welcome', 'Hello :name', ['name' => $n])
     */
    function __(string $key, ?string $default = null, array $replace = []): string
    {
        return Lang::get($key, $default, $replace);
    }
}

if (!function_exists('setting')) {
    /**
     * Global setting from the settings table (cached per request).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        static $settings = null;
        static $loaded = false;
        if (!$loaded) {
            $loaded = true;
            try {
                $rows = DB::table('settings')->get();
                $settings = [];
                foreach ($rows as $row) {
                    $settings[$row['key']] = $row['value'];
                }
            } catch (\Throwable) {
                $settings = []; // DB not available (installer)
            }
        }
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('set_setting')) {
    function set_setting(string $key, mixed $value): void
    {
        $stringValue = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $exists = DB::table('settings')->where('key', $key)->exists();
        if ($exists) {
            DB::table('settings')->where('key', $key)->update(['value' => $stringValue]);
        } else {
            DB::table('settings')->insert(['key' => $key, 'value' => $stringValue]);
        }
    }
}

if (!function_exists('user')) {
    function user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('tenant')) {
    function tenant(): ?array
    {
        return Tenant::current();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Flashed old input for form repopulation.
     */
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_flash_read']['old'][$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    /**
     * Read a flash value (moved from _flash to _flash_read on first access
     * per request so it survives exactly one redirect).
     */
    function flash(string $key, mixed $default = null): mixed
    {
        if (isset($_SESSION['_flash']) && !isset($_SESSION['_flash_moved'])) {
            $_SESSION['_flash_read'] = $_SESSION['_flash'];
            unset($_SESSION['_flash']);
            $_SESSION['_flash_moved'] = true;
        }
        return $_SESSION['_flash_read'][$key] ?? $default;
    }
}

if (!function_exists('flash_errors')) {
    /** @return array<string, array<int, string>> */
    function flash_errors(): array
    {
        $errors = flash('errors', []);
        return is_array($errors) ? $errors : [];
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('is_installed')) {
    function is_installed(): bool
    {
        return is_file(ROOT_PATH . '/installed.lock');
    }
}

if (!function_exists('app_version')) {
    function app_version(): string
    {
        static $version = null;
        if ($version === null) {
            $file = ROOT_PATH . '/version.json';
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
            $version = is_array($data) ? (string) ($data['version'] ?? '1.0.0') : '1.0.0';
        }
        return $version;
    }
}

if (!function_exists('audit_log')) {
    /**
     * Write an audit trail entry.
     */
    function audit_log(string $action, string $subjectType = '', ?int $subjectId = null, array $meta = []): void
    {
        try {
            DB::table('audit_logs')->insert([
                'tenant_id' => Tenant::id(),
                'user_id' => Auth::id(),
                'action' => mb_substr($action, 0, 191),
                'subject_type' => mb_substr($subjectType, 0, 100),
                'subject_id' => $subjectId,
                'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'ip' => PHP_SAPI === 'cli' ? null : \App\Core\Request::instance()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // audit logging must never break the request
        }
    }
}

if (!function_exists('format_money')) {
    function format_money(float|int|string $amount, string $currency = 'INR'): string
    {
        return \App\Core\Money::format((float) $amount, $currency);
    }
}

if (!function_exists('time_ago')) {
    function time_ago(string $datetime): string
    {
        return \App\Core\DateHelper::ago($datetime);
    }
}
