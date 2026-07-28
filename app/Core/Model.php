<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base model. Static-style access over the query builder with automatic
 * tenant scoping: models with $tenantScoped = true have `tenant_id = ?`
 * injected into every query built through query()/find()/etc.
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static bool $tenantScoped = false;
    protected static bool $timestamps = true;

    public static function table(): string
    {
        if (static::$table !== '') {
            return static::$table;
        }
        // Derive: App\Models\ContactField -> contact_fields
        $class = substr(strrchr(static::class, '\\') ?: static::class, 1);
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class) ?? $class);
        return $snake . 's';
    }

    /**
     * New query builder with tenant scope applied when relevant.
     */
    public static function query(): QueryBuilder
    {
        $builder = DB::table(static::table());
        if (static::$tenantScoped) {
            $tenantId = Tenant::id();
            if ($tenantId === null) {
                throw new \RuntimeException('Tenant context required for ' . static::class);
            }
            $builder->where(static::table() . '.tenant_id', $tenantId);
        }
        return $builder;
    }

    /**
     * Query WITHOUT tenant scoping — for system jobs and admin contexts.
     * Callers must scope explicitly where needed.
     */
    public static function unscoped(): QueryBuilder
    {
        return DB::table(static::table());
    }

    public static function find(int|string $id): ?array
    {
        return static::query()->where(static::table() . '.' . static::$primaryKey, $id)->first();
    }

    public static function findOrFail(int|string $id): array
    {
        $row = static::find($id);
        if ($row === null) {
            Response::abort(404);
        }
        return $row;
    }

    public static function where(string $column, mixed $operator = null, mixed $value = null): QueryBuilder
    {
        if (func_num_args() === 2) {
            return static::query()->where($column, $operator);
        }
        return static::query()->where($column, $operator, $value);
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function count(): int
    {
        return static::query()->count();
    }

    /**
     * Insert a row; auto-fills tenant_id and timestamps.
     */
    public static function create(array $data): int
    {
        if (static::$tenantScoped && !isset($data['tenant_id'])) {
            $tenantId = Tenant::id();
            if ($tenantId === null) {
                throw new \RuntimeException('Tenant context required for ' . static::class);
            }
            $data['tenant_id'] = $tenantId;
        }
        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] ??= $now;
            $data['updated_at'] ??= $now;
        }
        return DB::table(static::table())->insert($data);
    }

    public static function updateById(int|string $id, array $data): int
    {
        if (static::$timestamps) {
            $data['updated_at'] ??= date('Y-m-d H:i:s');
        }
        return static::query()->where(static::table() . '.' . static::$primaryKey, $id)->update($data);
    }

    public static function deleteById(int|string $id): int
    {
        return static::query()->where(static::table() . '.' . static::$primaryKey, $id)->delete();
    }
}
