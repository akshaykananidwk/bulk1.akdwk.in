<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Event;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\View;
use App\Models\Tenant as TenantModel;
use App\Models\User;

final class RegisterController extends Controller
{
    public function show(Request $request): never
    {
        if (setting('registration_enabled', '1') !== '1') {
            \App\Core\Response::abort(403, __('auth.registration_disabled', 'Registration is currently disabled.'));
        }
        View::render('auth/register', [], 'layouts/auth');
    }

    public function register(Request $request): never
    {
        if (setting('registration_enabled', '1') !== '1') {
            \App\Core\Response::abort(403);
        }

        $data = $this->validate($request, [
            'company' => 'required|string|min:2|max:100',
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|phone',
            'password' => 'required|password_strength|confirmed',
        ]);

        // Simple math captcha (configurable providers can be layered later)
        if ((string) setting('captcha_provider', 'math') === 'math') {
            $expected = (int) ($_SESSION['captcha_answer'] ?? -1);
            if ($request->int('captcha', -999) !== $expected) {
                Redirect::back('/register')
                    ->withErrors(['captcha' => [__('auth.captcha_failed', 'Captcha answer is incorrect.')]])
                    ->withInput()
                    ->send();
            }
        }

        $userId = DB::transaction(function () use ($data) {
            $tenantId = TenantModel::createWorkspace(
                (string) $data['company'],
                (string) $data['email'],
                $data['phone'] ?? null
            );
            $roleId = TenantModel::roleId($tenantId, 'owner');
            return User::register($tenantId, (string) $data['name'], (string) $data['email'], (string) $data['password'], $roleId);
        });

        $user = DB::table('users')->where('id', $userId)->first();
        if ($user !== null) {
            Event::fire('tenant.registered', ['user_id' => $userId, 'tenant_id' => $user['tenant_id']]);
            Auth::login($user);
            audit_log('auth.registered', 'user', $userId);
        }

        Redirect::to('/tenant')->with('success', __('auth.welcome', 'Welcome! Your workspace is ready.'))->send();
    }
}
