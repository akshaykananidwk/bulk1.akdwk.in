<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Hash;
use App\Core\Model;
use App\Core\Str;

final class Tenant extends Model
{
    protected static string $table = 'tenants';

    /**
     * Create a workspace with defaults: trial, wallet, cloned roles.
     */
    public static function createWorkspace(string $name, string $email, ?string $phone = null): int
    {
        $slug = self::uniqueSlug($name);

        $plan = DB::table('plans')->where('slug', (string) setting('default_plan_slug', 'trial'))->first()
            ?? DB::table('plans')->orderBy('sort_order')->first();
        $trialDays = (int) ($plan['trial_days'] ?? 7);

        return DB::transaction(function () use ($name, $email, $phone, $slug, $plan, $trialDays) {
            $tenantId = DB::table('tenants')->insert([
                'name' => $name,
                'slug' => $slug,
                'email' => strtolower($email),
                'phone' => $phone,
                'plan_id' => $plan['id'] ?? null,
                'status' => 'active',
                'timezone' => (string) config('app.timezone', 'Asia/Kolkata'),
                'country' => 'IN',
                'trial_ends_at' => date('Y-m-d H:i:s', time() + $trialDays * 86400),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('wallets')->insert([
                'tenant_id' => $tenantId,
                'balance' => 0,
                'currency' => (string) setting('default_currency', 'INR'),
                'updated_at' => now(),
            ]);

            // Clone system template roles for this tenant
            $systemRoles = DB::table('roles')->whereNull('tenant_id')->where('is_system', 1)->get();
            foreach ($systemRoles as $role) {
                $newRoleId = DB::table('roles')->insert([
                    'tenant_id' => $tenantId,
                    'name' => $role['name'],
                    'slug' => $role['slug'],
                    'description' => $role['description'],
                    'is_system' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $permissionIds = DB::table('role_permissions')->where('role_id', $role['id'])->pluck('permission_id');
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $newRoleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }

            return $tenantId;
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 1;
        while (DB::table('tenants')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    public static function roleId(int $tenantId, string $slug): ?int
    {
        $row = DB::table('roles')->where('tenant_id', $tenantId)->where('slug', $slug)->first();
        return $row !== null ? (int) $row['id'] : null;
    }
}
