<?php

declare(strict_types=1);

namespace App\Core;

/**
 * SQL migration runner. Executes database/migrations/*.sql not yet recorded
 * in the `migrations` table. Used by the updater and CLI.
 */
final class Migrator
{
    /**
     * Run pending migrations. Returns list of executed migration names.
     * Throws on first failure (caller handles rollback strategy).
     */
    public static function run(): array
    {
        self::ensureTable();

        $dir = ROOT_PATH . '/database/migrations';
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $applied = DB::table('migrations')->pluck('name');
        $appliedMap = array_flip($applied);

        $batch = (int) (DB::table('migrations')->max('batch') ?? 0) + 1;
        $executed = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (isset($appliedMap[$name])) {
                continue;
            }

            $started = microtime(true);
            $sql = (string) file_get_contents($file);
            self::runSqlBatch($sql);

            DB::table('migrations')->insert([
                'name' => $name,
                'batch' => $batch,
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'executed_at' => date('Y-m-d H:i:s'),
            ]);
            $executed[] = $name;
            Logger::channel('update')->info('Migration executed: ' . $name);
        }

        return $executed;
    }

    /**
     * Mark all current migration files as applied without running them
     * (fresh install: schema.sql already contains everything).
     */
    public static function markAllApplied(): int
    {
        self::ensureTable();
        $dir = ROOT_PATH . '/database/migrations';
        $files = is_dir($dir) ? (glob($dir . '/*.sql') ?: []) : [];
        sort($files, SORT_STRING);

        $applied = array_flip(DB::table('migrations')->pluck('name'));
        $count = 0;
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }
            DB::table('migrations')->insert([
                'name' => $name,
                'batch' => 0,
                'duration_ms' => 0,
                'executed_at' => date('Y-m-d H:i:s'),
            ]);
            $count++;
        }
        return $count;
    }

    /**
     * Execute a rollback SQL file for a migration if one is provided
     * (database/migrations/rollback/{name}.sql).
     */
    public static function rollbackMigration(string $name): bool
    {
        $file = ROOT_PATH . '/database/migrations/rollback/' . basename($name);
        if (!is_file($file)) {
            return false;
        }
        self::runSqlBatch((string) file_get_contents($file));
        DB::table('migrations')->where('name', basename($name))->delete();
        return true;
    }

    /**
     * Split an SQL batch on statement boundaries and execute each.
     * Handles semicolons inside quoted strings and DELIMITER-free files.
     */
    public static function runSqlBatch(string $sql): int
    {
        $statements = self::splitStatements($sql);
        $pdo = DB::connection();
        $count = 0;
        foreach ($statements as $statement) {
            $trimmed = trim($statement);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $pdo->exec(self::applyPrefix($trimmed));
            $count++;
        }
        return $count;
    }

    /**
     * Apply the configured table prefix to CREATE/ALTER/INSERT/etc statements.
     * With the default empty prefix this is a no-op.
     */
    public static function applyPrefix(string $sql): string
    {
        $prefix = DB::prefix();
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
     * Split SQL into statements, respecting quotes and comments.
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
                        $statements[] = $current;
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
            $statements[] = $current;
        }

        return $statements;
    }

    private static function ensureTable(): void
    {
        $prefix = DB::prefix();
        DB::connection()->exec(
            'CREATE TABLE IF NOT EXISTS `' . $prefix . 'migrations` ('
            . '`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
            . '`name` VARCHAR(191) NOT NULL UNIQUE,'
            . '`batch` INT NOT NULL DEFAULT 1,'
            . '`duration_ms` INT NOT NULL DEFAULT 0,'
            . '`executed_at` DATETIME NOT NULL'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
