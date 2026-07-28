<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\View;

final class LoginController extends Controller
{
    public function show(Request $request): never
    {
        View::render('auth/login', [], 'layouts/auth');
    }

    public function login(Request $request): never
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = Auth::attempt(
            (string) $data['email'],
            (string) $data['password'],
            $request->bool('remember'),
            $request->ip()
        );

        if ($user === null) {
            Redirect::back('/login')
                ->withErrors(['email' => [__('auth.failed', 'These credentials do not match our records, or too many attempts. Try again shortly.')]])
                ->withInput()
                ->send();
        }

        if (!empty($user['__2fa_required'])) {
            Redirect::to('/2fa')->send();
        }

        audit_log('auth.login', 'user', (int) $user['id']);

        $intended = (string) ($_SESSION['intended_url'] ?? '');
        unset($_SESSION['intended_url']);
        if ($intended !== '' && str_starts_with($intended, url('/'))) {
            Redirect::to($intended)->send();
        }

        Redirect::to(Auth::isSuperAdmin() ? '/admin' : '/tenant')->send();
    }

    public function logout(Request $request): never
    {
        audit_log('auth.logout');
        Auth::logout();
        Redirect::to('/login')->with('success', __('auth.logged_out', 'You have been logged out.'))->send();
    }

    public function stopImpersonating(Request $request): never
    {
        if (Auth::stopImpersonating()) {
            audit_log('auth.impersonation_stop');
            Redirect::to('/admin/tenants')->with('success', __('auth.impersonation_ended', 'Impersonation ended.'))->send();
        }
        Redirect::to('/')->send();
    }
}
