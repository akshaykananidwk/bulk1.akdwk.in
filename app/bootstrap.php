<?php
/**
 * Application bootstrap: autoloader, helpers, config, error handling, session.
 * Shared by the front controller, cron entrypoints and the installer runtime.
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
    define('APP_PATH', ROOT_PATH . '/app');
    define('CONFIG_PATH', ROOT_PATH . '/config');
    define('STORAGE_PATH', ROOT_PATH . '/storage');
    define('UPLOAD_PATH', ROOT_PATH . '/uploads');
    define('RESOURCE_PATH', ROOT_PATH . '/resources');
}

// ---------------------------------------------------------------------------
// PSR-4 style autoloader for the App\ namespace (no Composer required).
// Falls back to Composer's autoloader for the optional vendor packages.
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $relative = substr($class, 4);
        $file = APP_PATH . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

require_once APP_PATH . '/Helpers/functions.php';

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(STORAGE_PATH . '/logs') || @mkdir(STORAGE_PATH . '/logs', 0755, true)) {
    ini_set('error_log', STORAGE_PATH . '/logs/php_errors.log');
}

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// ---------------------------------------------------------------------------
// Configuration + timezone
// ---------------------------------------------------------------------------
\App\Core\Config::boot();

date_default_timezone_set((string) config('app.timezone', 'Asia/Kolkata'));

if (config('app.debug')) {
    ini_set('display_errors', '1');
}

// ---------------------------------------------------------------------------
// Session (web context only — skipped for CLI workers/cron)
// ---------------------------------------------------------------------------
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    $sessionPath = STORAGE_PATH . '/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    session_name('kwc_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => \App\Core\Request::isSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
