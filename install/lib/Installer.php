<?php

declare(strict_types=1);

namespace Installer;

use PDO;

/**
 * Installer engine: DB connection/creation, schema import with progress,
 * admin creation, config generation, self-locking.
 */
final class Installer
{
    public static function log(string $message): void
    {
        $dir = ROOT_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($dir . '/install.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    // -- Database ---------------------------------------------------------------

    public static function connect(array $db, bool $selectDatabase = true): PDO
    {
        $dsn = 'mysql:host=' . $db['host'] . ';port=' . (int) $db['port'] . ';charset=utf8mb4';
        if ($selectDatabase) {
            $dsn .= ';dbname=' . $db['database'];
        }
        return new PDO($dsn, (string) $db['username'], (string) $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Test connection; auto-create the database when missing (if permitted).
     * Returns [ok, message, existingTableCount].
     */
    public static function testDatabase(array $db): array
    {
        try {
            $pdo = self::connect($db, false);
        } catch (\Throwable $e) {
            return [false, 'Connection failed: ' . $e->getMessage(), 0];
        }

        $name = (string) $db['database'];
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            return [false, 'Database name may only contain letters, numbers and underscores.', 0];
        }

        try {
            $exists = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = " . $pdo->quote($name))->fetch();
            if (!$exists) {
                $pdo->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                self::log('Database created: ' . $name);
            }
        } catch (\Throwable $e) {
            return [false, 'Cannot create database "' . $name . '": ' . $e->getMessage() . ' — create it manually in aaPanel and retry.', 0];
        }

        try {
            $pdo = self::connect($db);
            $count = (int) $pdo->query('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ' . $pdo->quote($name))->fetch()['c'];
            return [true, $exists ? 'Connected. Database exists.' : 'Connected. Database created.', $count];
        } catch (\Throwable $e) {
            return [false, 'Connected to server but cannot use database: ' . $e->getMessage(), 0];
        }
    }

    // -- Import -------------------------------------------------------------------

    /**
     * Split schema/seed SQL into statements (same logic as App\Core\Migrator,
     * duplicated here so the installer works standalone before the app boots).
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $inString = false;
        $stringChar = '';
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }
                continue;
            }
            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }
            if (!$inString) {
                if ($char === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
                if ($char === "'" || $char === '"' || $char === '`') {
                    $inString = true;
                    $stringChar = $char;
                    $current .= $char;
                    continue;
                }
                if ($char === ';') {
                    if (trim($current) !== '') {
                        $statements[] = trim($current);
                    }
                    $current = '';
                    continue;
                }
            } else {
                if ($char === '\\' && $stringChar !== '`') {
                    $current .= $char . $next;
                    $i++;
                    continue;
                }
                if ($char === $stringChar) {
                    $inString = false;
                }
            }
            $current .= $char;
        }
        if (trim($current) !== '') {
            $statements[] = trim($current);
        }
        return $statements;
    }

    public static function applyPrefix(string $sql, string $prefix): string
    {
        if ($prefix === '') {
            return $sql;
        }
        return preg_replace(
            '/\b(CREATE TABLE(?: IF NOT EXISTS)?|ALTER TABLE|DROP TABLE(?: IF EXISTS)?|INSERT INTO|INSERT IGNORE INTO|UPDATE|DELETE FROM|REFERENCES|TRUNCATE TABLE)\s+`?([a-z0-9_]+)`?/i',
            '$1 `' . $prefix . '$2`',
            $sql
        ) ?? $sql;
    }

    /**
     * Build the ordered statement list for import (schema then seeds).
     * @return array<int, array{file:string, sql:string}>
     */
    public static function importPlan(): array
    {
        $plan = [];
        $files = [ROOT_PATH . '/database/schema.sql'];
        $seeds = glob(ROOT_PATH . '/database/seeds/*.sql') ?: [];
        sort($seeds, SORT_STRING);
        $files = array_merge($files, $seeds);

        foreach ($files as $file) {
            $sql = (string) @file_get_contents($file);
            foreach (self::splitStatements($sql) as $statement) {
                $plan[] = ['file' => basename($file), 'sql' => $statement];
            }
        }
        return $plan;
    }

    /**
     * Run a chunk of the import plan; state is tracked by offset.
     * Returns [done(bool), nextOffset, total, currentFile, error|null].
     */
    public static function importChunk(array $db, string $prefix, int $offset, int $chunkSize = 25): array
    {
        $plan = self::importPlan();
        $total = count($plan);
        $pdo = self::connect($db);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $end = min($offset + $chunkSize, $total);
        $file = '';
        for ($i = $offset; $i < $end; $i++) {
            $file = $plan[$i]['file'];
            $sql = self::applyPrefix($plan[$i]['sql'], $prefix);
            if (stripos($sql, 'SET FOREIGN_KEY_CHECKS') === 0 || stripos($sql, 'SET sql_mode') === 0) {
                continue;
            }
            try {
                $pdo->exec($sql);
            } catch (\Throwable $e) {
                self::log('Import error in ' . $file . ': ' . $e->getMessage());
                return [false, $i, $total, $file, $e->getMessage()];
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        self::log('Import progress: ' . $end . '/' . $total);

        return [$end >= $total, $end, $total, $file, null];
    }

    /**
     * Record all migration files as already-applied (fresh schema has them).
     */
    public static function markMigrationsApplied(array $db, string $prefix): int
    {
        $pdo = self::connect($db);
        $files = glob(ROOT_PATH . '/database/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);
        $count = 0;
        foreach ($files as $file) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO `' . $prefix . 'migrations` (`name`, `batch`, `duration_ms`, `executed_at`) VALUES (?, 0, 0, NOW())');
            $stmt->execute([basename($file)]);
            $count++;
        }
        return $count;
    }

    // -- Admin & settings -----------------------------------------------------------

    public static function createAdmin(array $db, string $prefix, array $admin): void
    {
        $pdo = self::connect($db);
        $hash = password_hash((string) $admin['password'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(
            'INSERT INTO `' . $prefix . 'users` (`tenant_id`, `name`, `email`, `phone`, `password`, `is_super_admin`, `status`, `timezone`, `created_at`, `updated_at`) '
            . 'VALUES (NULL, ?, ?, ?, ?, 1, "active", ?, NOW(), NOW()) '
            . 'ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `is_super_admin` = 1'
        );
        $stmt->execute([
            (string) $admin['name'],
            strtolower((string) $admin['email']),
            (string) ($admin['phone'] ?? ''),
            $hash,
            (string) ($admin['timezone'] ?? 'Asia/Kolkata'),
        ]);
        self::log('Admin account created: ' . strtolower((string) $admin['email']));
    }

    public static function applySettings(array $db, string $prefix, array $settings): void
    {
        $pdo = self::connect($db);
        $stmt = $pdo->prepare(
            'INSERT INTO `' . $prefix . 'settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        foreach ($settings as $key => $value) {
            $stmt->execute([(string) $key, (string) $value]);
        }
    }

    // -- Config generation ------------------------------------------------------------

    public static function writeConfig(array $app, array $db): void
    {
        $config = [
            'app' => [
                'name' => (string) $app['name'],
                'url' => rtrim((string) $app['url'], '/'),
                'key' => 'base64:' . base64_encode(random_bytes(32)),
                'env' => 'production',
                'debug' => false,
                'timezone' => (string) ($app['timezone'] ?? 'Asia/Kolkata'),
                'locale' => (string) ($app['locale'] ?? 'en'),
            ],
            'db' => [
                'host' => (string) $db['host'],
                'port' => (int) $db['port'],
                'database' => (string) $db['database'],
                'username' => (string) $db['username'],
                'password' => (string) $db['password'],
                'prefix' => (string) ($db['prefix'] ?? ''),
            ],
        ];

        $php = "<?php\n\n/**\n * Generated by the installer on " . date('Y-m-d H:i:s') . ".\n"
            . " * ⚠️ NEVER overwritten by the updater. Keep this file safe — it contains\n"
            . " * your database credentials and the APP_KEY used to encrypt all secrets.\n */\n\n"
            . 'return ' . var_export($config, true) . ";\n";

        if (@file_put_contents(CONFIG_PATH . '/config.php', $php, LOCK_EX) === false) {
            throw new \RuntimeException('Cannot write config/config.php — check permissions.');
        }
        @chmod(CONFIG_PATH . '/config.php', 0640);
        self::log('config/config.php written.');
    }

    /**
     * Finish: installed.lock + lock the installer directory.
     */
    public static function finish(string $version): array
    {
        $lock = [
            'installed_at' => date('c'),
            'version' => $version,
            'installation_id' => bin2hex(random_bytes(16)),
        ];
        file_put_contents(ROOT_PATH . '/installed.lock', json_encode($lock, JSON_PRETTY_PRINT));

        // Self-lock: rename install/ + block via .htaccess (belt and braces)
        $renamed = null;
        $target = ROOT_PATH . '/install_disabled_' . substr(bin2hex(random_bytes(6)), 0, 8);
        if (@rename(ROOT_PATH . '/install', $target)) {
            $renamed = basename($target);
            @file_put_contents($target . '/.htaccess', "Require all denied\n");
        } else {
            // Rename failed (open_basedir etc.) — hard-deny access instead
            @file_put_contents(ROOT_PATH . '/install/.htaccess', "Require all denied\n");
        }

        self::log('Installation finished. Installer locked' . ($renamed ? ' (renamed to ' . $renamed . ')' : ' (.htaccess deny)') . '.');
        return $lock;
    }

    /** Detected absolute paths for cron instructions. */
    public static function cronPaths(): array
    {
        $php = PHP_BINARY !== '' && !str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : '/usr/bin/php';
        return [
            'php' => $php,
            'scheduler' => ROOT_PATH . '/cron/scheduler.php',
            'worker' => ROOT_PATH . '/cron/worker.php',
            'campaign' => ROOT_PATH . '/cron/campaign.php',
            'watchdog' => ROOT_PATH . '/cron/watchdog.php',
        ];
    }
}
