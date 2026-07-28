<?php
/**
 * Krishna WhatsApp Cloud — One-Click Installer.
 * Upload files → open /install → done. No manual file editing, ever.
 */

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
    define('CONFIG_PATH', ROOT_PATH . '/config');
}

require __DIR__ . '/lib/RequirementChecker.php';
require __DIR__ . '/lib/Installer.php';

use Installer\Installer;
use Installer\RequirementChecker;

error_reporting(E_ALL);
ini_set('display_errors', '1');
// May be reached directly OR through the front controller (which already
// started a session) — only start our own when none is active.
if (session_status() === PHP_SESSION_NONE) {
    session_name('kwc_install');
    session_start();
}

// ---------------------------------------------------------------------------
// Already installed? Show the locked screen (repair requires DB password).
// ---------------------------------------------------------------------------
$alreadyInstalled = is_file(ROOT_PATH . '/installed.lock');
$action = $_GET['action'] ?? '';
$step = $_GET['step'] ?? 'welcome';

if ($alreadyInstalled && $action !== 'repair' && ($_SESSION['repair_unlocked'] ?? false) !== true) {
    $step = 'locked';
}

// CSRF for installer forms
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(24));
}
$csrf = (string) $_SESSION['install_csrf'];

function install_csrf_ok(): bool
{
    return hash_equals((string) ($_SESSION['install_csrf'] ?? ''), (string) ($_POST['_token'] ?? $_GET['_token'] ?? ''));
}

function install_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$e = fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

// ---------------------------------------------------------------------------
// AJAX actions
// ---------------------------------------------------------------------------
if ($action !== '' && $action !== 'repair') {
    if (!install_csrf_ok()) {
        install_json(['success' => false, 'message' => 'Session expired — refresh the page.'], 419);
    }

    switch ($action) {
        case 'test-db':
            $db = [
                'host' => trim((string) ($_POST['host'] ?? '127.0.0.1')),
                'port' => (int) ($_POST['port'] ?? 3306),
                'database' => trim((string) ($_POST['database'] ?? '')),
                'username' => trim((string) ($_POST['username'] ?? '')),
                'password' => (string) ($_POST['password'] ?? ''),
                'prefix' => trim((string) ($_POST['prefix'] ?? '')),
            ];
            if ($db['prefix'] !== '' && !preg_match('/^[a-z0-9_]{1,12}$/i', $db['prefix'])) {
                install_json(['success' => false, 'message' => 'Prefix may only contain letters/numbers/underscore (max 12).']);
            }
            [$ok, $message, $tables] = Installer::testDatabase($db);
            if ($ok) {
                $_SESSION['install_db'] = $db;
            }
            install_json(['success' => $ok, 'message' => $message, 'existing_tables' => $tables]);

        case 'import-chunk':
            $db = $_SESSION['install_db'] ?? null;
            if ($db === null) {
                install_json(['success' => false, 'message' => 'Database not configured.'], 422);
            }
            $offset = (int) ($_POST['offset'] ?? 0);
            [$done, $next, $total, $file, $error] = Installer::importChunk($db, (string) $db['prefix'], $offset);
            if ($error !== null) {
                install_json(['success' => false, 'message' => 'Error in ' . $file . ': ' . $error, 'offset' => $next, 'total' => $total]);
            }
            if ($done) {
                Installer::markMigrationsApplied($db, (string) $db['prefix']);
                $_SESSION['install_imported'] = true;
            }
            install_json(['success' => true, 'done' => $done, 'offset' => $next, 'total' => $total, 'file' => $file]);

        case 'save-admin':
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $timezone = (string) ($_POST['timezone'] ?? 'Asia/Kolkata');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
                install_json(['success' => false, 'message' => 'Fill all fields — password must be at least 8 characters.']);
            }
            if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                $timezone = 'Asia/Kolkata';
            }
            $_SESSION['install_admin'] = [
                'name' => $name, 'email' => $email, 'password' => $password,
                'phone' => trim((string) ($_POST['phone'] ?? '')), 'timezone' => $timezone,
            ];
            install_json(['success' => true]);

        case 'save-settings':
            $url = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                install_json(['success' => false, 'message' => 'App URL is not a valid URL.']);
            }
            $_SESSION['install_settings'] = [
                'app_name' => trim((string) ($_POST['app_name'] ?? 'Krishna WhatsApp Cloud')) ?: 'Krishna WhatsApp Cloud',
                'app_url' => $url,
                'locale' => in_array($_POST['locale'] ?? 'en', ['en', 'gu', 'hi'], true) ? (string) $_POST['locale'] : 'en',
                'currency' => in_array($_POST['currency'] ?? 'INR', ['INR', 'USD', 'EUR', 'GBP', 'AED'], true) ? (string) $_POST['currency'] : 'INR',
                'date_format' => (string) ($_POST['date_format'] ?? 'd M Y'),
            ];
            install_json(['success' => true]);

        case 'cron-status':
            $heartbeat = ROOT_PATH . '/storage/locks/cron_heartbeat';
            $age = is_file($heartbeat) ? time() - (int) @file_get_contents($heartbeat) : null;
            install_json(['success' => true, 'running' => $age !== null && $age < 120, 'age' => $age]);

        case 'finalize':
            $db = $_SESSION['install_db'] ?? null;
            $admin = $_SESSION['install_admin'] ?? null;
            $settings = $_SESSION['install_settings'] ?? null;
            if ($db === null || $admin === null || $settings === null || empty($_SESSION['install_imported'])) {
                install_json(['success' => false, 'message' => 'Earlier steps are incomplete.'], 422);
            }
            try {
                Installer::createAdmin($db, (string) $db['prefix'], $admin);
                Installer::applySettings($db, (string) $db['prefix'], [
                    'app_name' => $settings['app_name'],
                    'default_language' => $settings['locale'],
                    'default_currency' => $settings['currency'],
                    'date_format' => $settings['date_format'],
                    'alert_email' => $admin['email'],
                    'update_notify_email' => $admin['email'],
                    'worker_mode' => (string) ($_POST['worker_mode'] ?? 'cron'),
                ]);
                Installer::writeConfig([
                    'name' => $settings['app_name'],
                    'url' => $settings['app_url'],
                    'timezone' => $admin['timezone'],
                    'locale' => $settings['locale'],
                ], $db);

                $versionFile = ROOT_PATH . '/version.json';
                $versionData = is_file($versionFile) ? json_decode((string) file_get_contents($versionFile), true) : [];
                $lock = Installer::finish((string) ($versionData['version'] ?? '1.0.0'));

                // Clear caches
                foreach (glob(ROOT_PATH . '/storage/cache/*/*.cache') ?: [] as $file) {
                    @unlink($file);
                }

                session_destroy();
                install_json(['success' => true, 'installation_id' => $lock['installation_id'], 'login_url' => $settings['app_url'] . '/login']);
            } catch (Throwable $ex) {
                Installer::log('Finalize failed: ' . $ex->getMessage());
                install_json(['success' => false, 'message' => $ex->getMessage()], 500);
            }

        default:
            install_json(['success' => false, 'message' => 'Unknown action'], 404);
    }
}

// Repair unlock (already installed): verify DB password from config
if ($action === 'repair' && $alreadyInstalled && $_SERVER['REQUEST_METHOD'] === 'POST' && install_csrf_ok()) {
    $config = is_file(CONFIG_PATH . '/config.php') ? require CONFIG_PATH . '/config.php' : null;
    $expected = is_array($config) ? (string) ($config['db']['password'] ?? '') : '';
    if ($expected !== '' && hash_equals($expected, (string) ($_POST['db_password'] ?? ''))) {
        $_SESSION['repair_unlocked'] = true;
        header('Location: ./?step=welcome');
        exit;
    }
    $repairError = 'Incorrect database password.';
    $step = 'locked';
}

$serverInfo = [
    'PHP' => PHP_VERSION,
    'Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'OS' => PHP_OS_FAMILY,
    'Document root' => ROOT_PATH,
];
$cronPaths = Installer::cronPaths();
$detectedUrl = (function (): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
})();

$steps = ['welcome' => 1, 'requirements' => 2, 'database' => 3, 'import' => 4, 'admin' => 5, 'settings' => 6, 'cron' => 7, 'finish' => 8];
$stepNumber = $steps[$step] ?? 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install — Krishna WhatsApp Cloud</title>
    <link rel="stylesheet" href="assets/install.css">
</head>
<body>
<div class="wrap">
    <header class="header">
        <div class="logo">🦚 <strong>Krishna WhatsApp Cloud</strong> <span class="muted">Installer</span></div>
        <?php if ($step !== 'locked'): ?>
            <ol class="stepper">
                <?php foreach (['Welcome', 'Requirements', 'Database', 'Import', 'Admin', 'Settings', 'Cron', 'Finish'] as $i => $label): ?>
                    <li class="<?= $i + 1 < $stepNumber ? 'done' : ($i + 1 === $stepNumber ? 'active' : '') ?>"><?= $e($label) ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </header>

    <main class="panel">
        <?php
        $stepFile = __DIR__ . '/steps/' . preg_replace('/[^a-z]/', '', $step) . '.php';
        if (is_file($stepFile)) {
            include $stepFile;
        } else {
            include __DIR__ . '/steps/welcome.php';
        }
        ?>
    </main>

    <footer class="footer muted">Krishna SaaS Suite · akdwk.in</footer>
</div>
<script>window.INSTALL_CSRF = <?= json_encode($csrf) ?>;</script>
<script src="assets/install.js"></script>
</body>
</html>
