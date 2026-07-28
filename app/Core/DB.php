<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO singleton + fluent query builder.
 *
 * Every query uses prepared statements. DB::raw() is only ever called with
 * internal, developer-authored SQL fragments — never with user input.
 */
final class DB
{
    private static ?PDO $pdo = null;
    private static int $transactionLevel = 0;
    private static string $prefix = '';

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$pdo;
    }

    private static function connect(): void
    {
        $host = (string) Config::get('db.host', '127.0.0.1');
        $port = (int) Config::get('db.port', 3306);
        $name = (string) Config::get('db.database', '');
        $user = (string) Config::get('db.username', '');
        $pass = (string) Config::get('db.password', '');
        self::$prefix = (string) Config::get('db.prefix', '');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $attempts = 0;
        while (true) {
            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
                ]);
                return;
            } catch (PDOException $e) {
                $attempts++;
                if ($attempts >= 3) {
                    throw $e;
                }
                usleep(200000 * $attempts);
            }
        }
    }

    public static function prefix(): string
    {
        if (self::$pdo === null) {
            self::$prefix = (string) Config::get('db.prefix', '');
        }
        return self::$prefix;
    }

    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    /**
     * Run a raw prepared query. $sql is always developer-authored.
     */
    public static function query(string $sql, array $bindings = []): PDOStatement
    {
        $start = microtime(true);
        try {
            $stmt = self::connection()->prepare($sql);
            $stmt->execute(array_values($bindings) === $bindings ? $bindings : $bindings);
        } catch (PDOException $e) {
            // Retry once on "server has gone away"
            if (str_contains($e->getMessage(), 'gone away') || (int) $e->errorInfo[1] === 2006) {
                self::$pdo = null;
                $stmt = self::connection()->prepare($sql);
                $stmt->execute($bindings);
            } else {
                throw $e;
            }
        }

        $elapsed = (microtime(true) - $start) * 1000;
        if ($elapsed > 500) {
            Logger::channel('slow')->warning(sprintf('%.1fms', $elapsed) . ' ' . mb_substr($sql, 0, 500));
        }

        return $stmt;
    }

    public static function select(string $sql, array $bindings = []): array
    {
        return self::query($sql, $bindings)->fetchAll();
    }

    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::query($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    public static function statement(string $sql, array $bindings = []): int
    {
        return self::query($sql, $bindings)->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::connection()->lastInsertId();
    }

    /**
     * Run a callback inside a transaction (nested calls use savepoints).
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();

        if (self::$transactionLevel === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT sp_' . self::$transactionLevel);
        }
        self::$transactionLevel++;

        try {
            $result = $callback();
            self::$transactionLevel--;
            if (self::$transactionLevel === 0) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT sp_' . self::$transactionLevel);
            }
            return $result;
        } catch (\Throwable $e) {
            self::$transactionLevel--;
            if (self::$transactionLevel === 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                $pdo->exec('ROLLBACK TO SAVEPOINT sp_' . self::$transactionLevel);
            }
            throw $e;
        }
    }

    /**
     * Wrap a developer-authored SQL fragment so the builder treats it verbatim.
     * NEVER pass user input into this.
     */
    public static function raw(string $expression): RawExpression
    {
        return new RawExpression($expression);
    }

    /**
     * Whether the server supports SKIP LOCKED (MySQL 8+/MariaDB 10.6+).
     */
    public static function supportsSkipLocked(): bool
    {
        static $supported = null;
        if ($supported !== null) {
            return $supported;
        }
        try {
            $version = (string) self::connection()->getAttribute(PDO::ATTR_SERVER_VERSION);
            if (stripos($version, 'mariadb') !== false) {
                preg_match('/(\d+)\.(\d+)/', $version, $m);
                $supported = isset($m[1]) && ((int) $m[1] > 10 || ((int) $m[1] === 10 && (int) $m[2] >= 6));
            } else {
                $supported = version_compare($version, '8.0.0', '>=');
            }
        } catch (\Throwable) {
            $supported = false;
        }
        return $supported;
    }
}

/**
 * Marker class for whitelisted raw SQL fragments.
 */
final class RawExpression
{
    public function __construct(public readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
