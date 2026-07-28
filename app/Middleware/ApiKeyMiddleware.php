<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\DB;
use App\Core\Hash;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;

/**
 * API authentication via X-Api-Key header (or Bearer token) with per-key
 * scopes and rate limits. Sets tenant context on success.
 */
final class ApiKeyMiddleware
{
    public function handle(Request $request, callable $next, string $scope = ''): void
    {
        $key = (string) ($request->header('X-Api-Key') ?? $request->bearerToken() ?? '');
        if ($key === '' || !str_contains($key, '.')) {
            Response::json(['success' => false, 'message' => 'Missing or malformed API key.'], 401);
        }

        [$keyId, $secret] = explode('.', $key, 2);
        $row = DB::table('api_keys')->where('key_id', $keyId)->where('status', 'active')->first();
        if ($row === null || !hash_equals((string) $row['secret_hash'], hash('sha256', $secret))) {
            Response::json(['success' => false, 'message' => 'Invalid API key.'], 401);
        }

        if ($row['expires_at'] !== null && $row['expires_at'] < date('Y-m-d H:i:s')) {
            Response::json(['success' => false, 'message' => 'API key expired.'], 401);
        }

        // Scope check
        if ($scope !== '') {
            $scopes = json_decode((string) ($row['scopes'] ?? '[]'), true) ?: [];
            if (!in_array('*', $scopes, true) && !in_array($scope, $scopes, true)) {
                Response::json(['success' => false, 'message' => 'API key lacks scope: ' . $scope], 403);
            }
        }

        // Per-key rate limit (per minute)
        $limit = (int) ($row['rate_limit'] ?? 60);
        if ($limit > 0 && !RateLimiter::attempt('apikey:' . $row['id'], $limit, 60)) {
            Response::json(['success' => false, 'message' => 'API rate limit exceeded.'], 429, [
                'Retry-After' => '60',
            ]);
        }

        Tenant::setId((int) $row['tenant_id']);
        DB::table('api_keys')->where('id', $row['id'])->update(['last_used_at' => date('Y-m-d H:i:s')]);

        // Request log (pruned by scheduler)
        try {
            DB::table('api_logs')->insert([
                'tenant_id' => (int) $row['tenant_id'],
                'api_key_id' => (int) $row['id'],
                'method' => $request->method(),
                'path' => mb_substr($request->path(), 0, 255),
                'ip' => $request->ip(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // never block API on logging
        }

        $next();
    }
}
