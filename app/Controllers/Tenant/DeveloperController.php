<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Hash;
use App\Core\Layout;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\View;

/**
 * API keys + outbound webhooks management for tenants.
 */
final class DeveloperController extends Controller
{
    public const SCOPES = [
        'messages.send', 'messages.read',
        'contacts.read', 'contacts.write',
        'templates.read', 'conversations.read',
        '*',
    ];

    public function index(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        Layout::title(__('nav.developers', 'API & Webhooks'));
        View::render('tenant/developers', [
            'keys' => DB::table('api_keys')->where('tenant_id', $tenantId)->orderBy('id', 'DESC')->get(),
            'hooks' => DB::table('webhooks')->where('tenant_id', $tenantId)->orderBy('id', 'DESC')->get(),
            'scopes' => self::SCOPES,
            'apiBase' => url('/api/v1'),
            'docsUrl' => url('/api/docs'),
        ], 'layouts/tenant');
    }

    public function createKey(Request $request): never
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:100',
            'rate_limit' => 'nullable|integer|between:1,10000',
        ]);

        $scopes = array_values(array_intersect($request->arr('scopes'), self::SCOPES)) ?: ['messages.send'];

        $keyId = 'kwc_' . Hash::token(10);
        $secret = Hash::token(32);

        DB::table('api_keys')->insert([
            'tenant_id' => (int) Tenant::id(),
            'name' => (string) $data['name'],
            'key_id' => $keyId,
            'secret_hash' => hash('sha256', $secret),
            'scopes' => json_encode($scopes),
            'rate_limit' => (int) ($data['rate_limit'] ?? 60),
            'status' => 'active',
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        audit_log('api_keys.created');
        // Full key shown ONCE — only the hash is stored
        $this->ok([
            'api_key' => $keyId . '.' . $secret,
            'note' => __('api.key_once', 'Copy this key now — it will never be shown again.'),
        ]);
    }

    public function revokeKey(Request $request): never
    {
        $key = DB::table('api_keys')
            ->where('id', (int) $request->route('id'))
            ->where('tenant_id', Tenant::id())
            ->first();
        if ($key === null) {
            Response::abort(404);
        }
        DB::table('api_keys')->where('id', $key['id'])->update(['status' => 'revoked', 'updated_at' => now()]);
        audit_log('api_keys.revoked', 'api_key', (int) $key['id']);
        \App\Core\Redirect::to('/tenant/developers')->with('success', __('api.key_revoked', 'API key revoked.'))->send();
    }
}
