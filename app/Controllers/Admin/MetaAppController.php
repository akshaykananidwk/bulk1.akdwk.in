<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Crypt;
use App\Core\DB;
use App\Core\Hash;
use App\Core\Layout;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\View;

/**
 * Global Meta App configuration (super admin): app id/secret, config id,
 * system user token, API version, webhook verify token.
 */
final class MetaAppController extends Controller
{
    public function index(Request $request): never
    {
        $app = DB::table('meta_apps')->orderBy('is_default', 'DESC')->first();

        Layout::title(__('admin.meta_app', 'Meta App'));
        View::render('admin/meta-app', [
            'app' => $app,
            'maskedSecret' => $app !== null ? Crypt::mask('secret••••' . substr((string) $app['app_id'], -4)) : null,
            'webhookUrl' => url('/webhook/meta'),
        ], 'layouts/admin');
    }

    public function save(Request $request): never
    {
        $data = $this->validate($request, [
            'app_id' => 'required|string|max:64',
            'app_secret' => 'nullable|string|max:255',
            'config_id' => 'nullable|string|max:64',
            'system_user_token' => 'nullable|string',
            'api_version' => 'required|regex:/^v\d+\.\d+$/',
            'webhook_verify_token' => 'nullable|string|max:191',
        ]);

        $existing = DB::table('meta_apps')->orderBy('is_default', 'DESC')->first();

        $row = [
            'app_id' => (string) $data['app_id'],
            'config_id' => $data['config_id'] ?? null,
            'api_version' => (string) $data['api_version'],
            'updated_at' => now(),
        ];

        // Secrets only overwritten when a new value is supplied (masked in UI)
        if (!empty($data['app_secret'])) {
            $row['app_secret_encrypted'] = Crypt::encrypt((string) $data['app_secret']);
        }
        if (!empty($data['system_user_token'])) {
            $row['system_user_token_encrypted'] = Crypt::encrypt((string) $data['system_user_token']);
        }
        if (!empty($data['webhook_verify_token'])) {
            $row['webhook_verify_token'] = (string) $data['webhook_verify_token'];
        }

        if ($existing !== null) {
            DB::table('meta_apps')->where('id', $existing['id'])->update($row);
        } else {
            if (empty($row['app_secret_encrypted'])) {
                Redirect::back('/admin/meta-app')->with('error', __('admin.meta_secret_required', 'App secret is required for first-time setup.'))->send();
            }
            $row['webhook_verify_token'] = $row['webhook_verify_token'] ?? Hash::token(24);
            $row['is_default'] = 1;
            $row['name'] = 'Default';
            $row['created_at'] = now();
            DB::table('meta_apps')->insert($row);
        }

        set_setting('meta_api_version', (string) $data['api_version']);
        audit_log('admin.meta_app_saved');
        Redirect::to('/admin/meta-app')->with('success', __('admin.meta_saved', 'Meta App settings saved.'))->send();
    }
}
