<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Hash;
use App\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';

    public static function findByEmail(string $email): ?array
    {
        return DB::table('users')->where('email', strtolower($email))->first();
    }

    public static function register(int $tenantId, string $name, string $email, string $password, ?int $roleId = null): int
    {
        return DB::table('users')->insert([
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'name' => $name,
            'email' => strtolower($email),
            'password' => Hash::make($password),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Agents of a tenant (for assignment dropdowns) with presence.
     */
    public static function agentsOf(int $tenantId): array
    {
        return DB::table('users u')
            ->leftJoin('agent_presence', 'u.id', '=', 'agent_presence.user_id')
            ->where('u.tenant_id', $tenantId)
            ->where('u.status', 'active')
            ->select('u.id', 'u.name', 'u.email', 'u.avatar', 'agent_presence.status')
            ->orderBy('u.name')
            ->get();
    }
}
