<?php
/**
 * Krishna WhatsApp Cloud — Front Controller
 * All HTTP requests are routed through this file (see .htaccess).
 */

declare(strict_types=1);

define('KWC_START', microtime(true));
define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('RESOURCE_PATH', ROOT_PATH . '/resources');

require APP_PATH . '/bootstrap.php';

use App\Core\Router;
use App\Core\Request;
use App\Core\Response;
use App\Core\Logger;

$request = Request::capture();

// ---------------------------------------------------------------------------
// Installer gate: if the app is not installed, everything goes to /install
// ---------------------------------------------------------------------------
$installed = is_file(ROOT_PATH . '/installed.lock') && is_file(CONFIG_PATH . '/config.php');
$uri = $request->path();

if (!$installed) {
    if (str_starts_with($uri, '/install')) {
        // Let Apache serve /install/index.php directly; if rewritten here, include it.
        require ROOT_PATH . '/install/index.php';
        exit;
    }
    Response::redirect('/install/');
}

// ---------------------------------------------------------------------------
// Maintenance mode (written by the updater). Allowed IPs pass through.
// ---------------------------------------------------------------------------
$maintenanceFlag = STORAGE_PATH . '/maintenance.flag';
if (is_file($maintenanceFlag) && !str_starts_with($uri, '/webhook/')) {
    $allowed = [];
    $raw = @file_get_contents($maintenanceFlag);
    if ($raw !== false) {
        $data = json_decode($raw, true);
        $allowed = is_array($data['allowed_ips'] ?? null) ? $data['allowed_ips'] : [];
    }
    if (!in_array($request->ip(), $allowed, true)) {
        http_response_code(503);
        header('Retry-After: 120');
        $view = RESOURCE_PATH . '/views/errors/503.php';
        if (is_file($view)) {
            include $view;
        } else {
            echo 'Service temporarily unavailable. Update in progress.';
        }
        exit;
    }
}

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------
try {
    $router = Router::instance();

    foreach (['web', 'public', 'auth', 'admin', 'tenant', 'agent', 'api', 'webhook'] as $file) {
        $path = ROOT_PATH . '/routes/' . $file . '.php';
        if (is_file($path)) {
            require $path;
        }
    }

    $router->dispatch($request);
} catch (\Throwable $e) {
    Logger::channel('error')->error($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'uri' => $uri,
        'trace' => config('app.debug') ? $e->getTraceAsString() : null,
    ]);

    if ($request->wantsJson()) {
        Response::json([
            'success' => false,
            'message' => config('app.debug') ? $e->getMessage() : 'Internal server error',
        ], 500);
    }

    http_response_code(500);
    $view = RESOURCE_PATH . '/views/errors/500.php';
    if (is_file($view)) {
        $error = config('app.debug') ? $e : null;
        include $view;
    } else {
        echo 'Internal server error.';
    }
}
