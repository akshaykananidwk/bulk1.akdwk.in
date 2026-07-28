<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Current-tenant resolver. Resolution order:
 *   1. Explicitly set (queue workers, jobs)
 *   2. Custom domain (tenant_domains table)
 *   3. Subdomain of the app's base domain
 *   4. Authenticated user's tenant_id (session)
 */
final class Tenant
{
    private static ?int $id = null;
    private static ?array $tenant = null;
    private static bool $resolved = false;

    public static function setId(?int $id): void
    {
        self::$id = $id;
        self::$tenant = null;
        self::$resolved = $id !== null;
    }

    public static function id(): ?int
    {
        if (self::$resolved) {
            return self::$id;
        }
        self::resolve();
        return self::$id;
    }

    public static function current(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        if (self::$tenant === null || (int) (self::$tenant['id'] ?? 0) !== $id) {
            self::$tenant = DB::table('tenants')->where('id', $id)->first();
        }
        return self::$tenant;
    }

    private static function resolve(): void
    {
        self::$resolved = true;

        if (PHP_SAPI === 'cli') {
            return; // workers set tenant explicitly per job
        }

        $host = Request::instance()->host();
        $baseDomain = self::baseDomain();

        try {
            // 1. Custom domain
            $domain = DB::table('tenant_domains')
                ->where('domain', $host)
                ->where('status', 'verified')
                ->first();
            if ($domain !== null) {
                self::$id = (int) $domain['tenant_id'];
                return;
            }

            // 2. Subdomain: {slug}.basedomain
            if ($baseDomain !== '' && $host !== $baseDomain && str_ends_with($host, '.' . $baseDomain)) {
                $slug = substr($host, 0, -strlen('.' . $baseDomain));
                if ($slug !== '' && !in_array($slug, ['www', 'app', 'admin', 'api'], true)) {
                    $tenant = DB::table('tenants')->where('slug', $slug)->first();
                    if ($tenant !== null) {
                        self::$id = (int) $tenant['id'];
                        self::$tenant = $tenant;
                        return;
                    }
                }
            }

            // 3. Authenticated user's tenant
            $user = Auth::user();
            if ($user !== null && !empty($user['tenant_id'])) {
                self::$id = (int) $user['tenant_id'];
            }
        } catch (\Throwable) {
            // DB not ready (installer) — leave null
        }
    }

    public static function baseDomain(): string
    {
        $url = (string) config('app.url', '');
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        return strtolower(preg_replace('/^www\./', '', $host) ?? $host);
    }

    /**
     * Read a tenant setting with fallback to global settings.
     */
    public static function setting(string $key, mixed $default = null): mixed
    {
        $id = self::id();
        if ($id === null) {
            return setting($key, $default);
        }
        static $cache = [];
        if (!isset($cache[$id])) {
            $rows = DB::table('tenant_settings')->where('tenant_id', $id)->get();
            $cache[$id] = [];
            foreach ($rows as $row) {
                $cache[$id][$row['key']] = $row['value'];
            }
        }
        return $cache[$id][$key] ?? setting($key, $default);
    }

    public static function setSetting(string $key, mixed $value): void
    {
        $id = self::id();
        if ($id === null) {
            return;
        }
        $exists = DB::table('tenant_settings')->where('tenant_id', $id)->where('key', $key)->exists();
        if ($exists) {
            DB::table('tenant_settings')->where('tenant_id', $id)->where('key', $key)
                ->update(['value' => is_scalar($value) || $value === null ? (string) $value : json_encode($value)]);
        } else {
            DB::table('tenant_settings')->insert([
                'tenant_id' => $id,
                'key' => $key,
                'value' => is_scalar($value) || $value === null ? (string) $value : json_encode($value),
            ]);
        }
    }

    /**
     * Plan limit check: returns [allowed(bool), limit(int|null), used(int)].
     * $feature examples: contacts, messages_monthly, agents, campaigns.
     */
    public static function withinLimit(string $feature, int $increment = 1): array
    {
        $tenant = self::current();
        if ($tenant === null) {
            return [false, 0, 0];
        }

        $planId = (int) ($tenant['plan_id'] ?? 0);
        $limit = null;
        if ($planId > 0) {
            $row = DB::table('plan_features')
                ->where('plan_id', $planId)
                ->where('feature', $feature)
                ->first();
            if ($row !== null) {
                $limit = $row['value'] === null || $row['value'] === '-1' ? null : (int) $row['value'];
            }
        }

        if ($limit === null) {
            return [true, null, 0]; // unlimited / not restricted
        }

        $used = self::usage($feature);
        return [$used + $increment <= $limit, $limit, $used];
    }

    public static function usage(string $feature): int
    {
        $id = self::id();
        if ($id === null) {
            return 0;
        }
        $period = date('Y-m');
        $row = DB::table('subscription_usage')
            ->where('tenant_id', $id)
            ->where('feature', $feature)
            ->where('period', $period)
            ->first();
        return $row !== null ? (int) $row['used'] : 0;
    }

    public static function recordUsage(string $feature, int $amount = 1): void
    {
        $id = self::id();
        if ($id === null) {
            return;
        }
        $period = date('Y-m');
        $updated = DB::table('subscription_usage')
            ->where('tenant_id', $id)
            ->where('feature', $feature)
            ->where('period', $period)
            ->increment('used', $amount);
        if ($updated === 0) {
            try {
                DB::table('subscription_usage')->insert([
                    'tenant_id' => $id,
                    'feature' => $feature,
                    'period' => $period,
                    'used' => $amount,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable) {
                // race: another request inserted first — increment instead
                DB::table('subscription_usage')
                    ->where('tenant_id', $id)
                    ->where('feature', $feature)
                    ->where('period', $period)
                    ->increment('used', $amount);
            }
        }
    }
}
