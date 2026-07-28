<?php

declare(strict_types=1);

namespace App\Services\Updater;

use App\Core\DB;
use App\Core\Logger;

/**
 * Backups: file zip (excluding uploads/backups/tmp/cache/.git) and a
 * pure-PHP mysqldump (no exec) with gzip streaming and 500-row chunks.
 */
final class BackupService
{
    public const EXCLUDED_DIRS = [
        'uploads', 'storage/backups', 'storage/tmp', 'storage/cache',
        'storage/sessions', 'storage/logs', '.git', 'node_modules',
    ];

    /**
     * Zip the application files. Returns [path, sizeBytes, backupId].
     */
    public static function filesBackup(string $trigger = 'pre_update'): array
    {
        @set_time_limit(0);
        $dir = STORAGE_PATH . '/backups';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Cannot create backups directory.');
        }

        $path = $dir . '/' . date('Ymd_His') . '_files.zip';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create backup zip: ' . $path);
        }

        $root = realpath(ROOT_PATH);
        if ($root === false) {
            throw new \RuntimeException('Cannot resolve root path.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $file) use ($root): bool {
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                    foreach (self::EXCLUDED_DIRS as $excluded) {
                        if ($relative === $excluded || str_starts_with($relative, $excluded . '/')) {
                            return false;
                        }
                    }
                    return true;
                }
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $count = 0;
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($file->isDir()) {
                $zip->addEmptyDir($relative);
            } elseif ($file->isFile()) {
                $zip->addFile($file->getPathname(), $relative);
                $count++;
                if ($count % 400 === 0) {
                    @set_time_limit(120);
                }
            }
        }
        $zip->close();

        $size = (int) @filesize($path);
        $backupId = DB::table('backups')->insert([
            'type' => 'files',
            'trigger_type' => in_array($trigger, ['manual', 'scheduled', 'pre_update'], true) ? $trigger : 'manual',
            'path' => $path,
            'size_bytes' => $size,
            'status' => 'completed',
            'created_at' => now(),
        ]);
        Logger::channel('update')->info('Files backup created', ['path' => basename($path), 'files' => $count, 'size' => $size]);

        return [$path, $size, $backupId];
    }

    /**
     * Pure-PHP mysqldump → gzip. Returns [path, sizeBytes, backupId].
     */
    public static function databaseBackup(string $trigger = 'pre_update'): array
    {
        @set_time_limit(0);
        $dir = STORAGE_PATH . '/backups';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new \RuntimeException('Cannot create backups directory.');
        }

        $path = $dir . '/' . date('Ymd_His') . '_db.sql.gz';
        $gz = gzopen($path, 'wb6');
        if ($gz === false) {
            throw new \RuntimeException('Cannot open backup file for writing.');
        }

        $pdo = DB::connection();
        $database = (string) \App\Core\Config::get('db.database');

        gzwrite($gz, "-- Krishna WhatsApp Cloud database backup\n");
        gzwrite($gz, '-- Database: ' . $database . ' · Generated: ' . date('c') . "\n\n");
        gzwrite($gz, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_NUM);
        foreach ($tables as [$table]) {
            @set_time_limit(120);
            $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '', (string) $table) . '`')->fetch();
            $createSql = (string) ($create['Create Table'] ?? '');
            gzwrite($gz, 'DROP TABLE IF EXISTS `' . $table . "`;\n" . $createSql . ";\n\n");

            // Batched inserts: 500 rows per statement
            $offset = 0;
            while (true) {
                $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '', (string) $table) . '` LIMIT 500 OFFSET ' . $offset);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                if (empty($rows)) {
                    break;
                }
                $columns = array_keys($rows[0]);
                $values = [];
                foreach ($rows as $row) {
                    $quoted = array_map(function ($value) use ($pdo) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        return $pdo->quote((string) $value);
                    }, array_values($row));
                    $values[] = '(' . implode(',', $quoted) . ')';
                }
                gzwrite($gz, 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES ' . "\n" . implode(",\n", $values) . ";\n");
                $offset += 500;
                if (count($rows) < 500) {
                    break;
                }
            }
            gzwrite($gz, "\n");
        }

        gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
        gzclose($gz);

        $size = (int) @filesize($path);
        $backupId = DB::table('backups')->insert([
            'type' => 'database',
            'trigger_type' => in_array($trigger, ['manual', 'scheduled', 'pre_update'], true) ? $trigger : 'manual',
            'path' => $path,
            'size_bytes' => $size,
            'status' => 'completed',
            'created_at' => now(),
        ]);
        Logger::channel('update')->info('Database backup created', ['path' => basename($path), 'size' => $size, 'tables' => count($tables)]);

        return [$path, $size, $backupId];
    }

    /**
     * Restore files from a backup zip (used by rollback).
     */
    public static function restoreFiles(string $zipPath): void
    {
        @set_time_limit(0);
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('Cannot open backup zip: ' . $zipPath);
        }

        $root = realpath(ROOT_PATH);
        if ($root === false) {
            throw new \RuntimeException('Cannot resolve root path.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            // Zip-slip guard on restore too
            if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
                continue;
            }
            $target = $root . '/' . $name;
            if (str_ends_with($name, '/')) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
                continue;
            }
            $directory = dirname($target);
            if (!is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }
            $contents = $zip->getFromIndex($i);
            if ($contents === false) {
                continue;
            }
            // Atomic per-file write
            @file_put_contents($target . '.kwctmp', $contents);
            @rename($target . '.kwctmp', $target);
        }
        $zip->close();
        Logger::channel('update')->info('Files restored from backup', ['zip' => basename($zipPath)]);
    }

    /**
     * Restore the database from a .sql.gz dump (used by rollback).
     */
    public static function restoreDatabase(string $gzPath): void
    {
        @set_time_limit(0);
        $gz = gzopen($gzPath, 'rb');
        if ($gz === false) {
            throw new \RuntimeException('Cannot open database backup: ' . $gzPath);
        }

        $pdo = DB::connection();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $buffer = '';
        while (!gzeof($gz)) {
            $buffer .= gzread($gz, 1048576);
            // Execute complete statements from the buffer
            while (($pos = self::statementEnd($buffer)) !== null) {
                $statement = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($statement !== '' && !str_starts_with($statement, '--')) {
                    $pdo->exec($statement);
                }
            }
        }
        $tail = trim($buffer);
        if ($tail !== '' && !str_starts_with($tail, '--')) {
            $pdo->exec($tail);
        }
        gzclose($gz);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        Logger::channel('update')->info('Database restored from backup', ['dump' => basename($gzPath)]);
    }

    /**
     * Find the end of the first complete SQL statement in the buffer
     * (semicolon at end-of-line, outside quoted strings).
     */
    private static function statementEnd(string $buffer): ?int
    {
        $inString = false;
        $stringChar = '';
        $length = strlen($buffer);
        for ($i = 0; $i < $length; $i++) {
            $char = $buffer[$i];
            if ($inString) {
                if ($char === '\\') {
                    $i++;
                    continue;
                }
                if ($char === $stringChar) {
                    $inString = false;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $inString = true;
                $stringChar = $char;
                continue;
            }
            if ($char === ';' && ($i + 1 >= $length || $buffer[$i + 1] === "\n" || $buffer[$i + 1] === "\r")) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Keep only the newest N backups per type.
     */
    public static function prune(int $keep = 5): int
    {
        $removed = 0;
        foreach (['files', 'database'] as $type) {
            $old = DB::table('backups')
                ->where('type', $type)
                ->where('status', 'completed')
                ->orderBy('id', 'DESC')
                ->offset($keep)
                ->limit(100)
                ->get();
            foreach ($old as $backup) {
                if (is_file((string) $backup['path'])) {
                    @unlink((string) $backup['path']);
                }
                DB::table('backups')->where('id', $backup['id'])->update(['status' => 'deleted']);
                $removed++;
            }
        }
        return $removed;
    }
}
