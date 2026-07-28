<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Hash;
use App\Core\Lang;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Storage;
use App\Core\View;

final class ProfileController extends Controller
{
    public function show(Request $request): never
    {
        View::render('auth/profile', [
            'profile' => Auth::user(),
            'languages' => Lang::available(),
        ], Auth::isSuperAdmin() ? 'layouts/admin' : 'layouts/tenant');
    }

    public function update(Request $request): never
    {
        $user = Auth::user();
        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:100',
            'phone' => 'nullable|phone',
            'timezone' => 'nullable|timezone',
        ]);

        $update = [
            'name' => (string) $data['name'],
            'phone' => $data['phone'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'updated_at' => now(),
        ];

        $avatar = $request->file('avatar');
        if ($avatar !== null) {
            $path = Storage::putUpload($avatar, 'avatars', ['jpg', 'jpeg', 'png', 'webp'], 2 * 1024 * 1024);
            $update['avatar'] = $path;
        }

        DB::table('users')->where('id', $user['id'])->update($update);
        audit_log('profile.updated');
        Redirect::to('/profile')->with('success', __('profile.saved', 'Profile updated.'))->send();
    }

    public function changePassword(Request $request): never
    {
        $user = Auth::user();
        $data = $this->validate($request, [
            'current_password' => 'required|string',
            'password' => 'required|password_strength|confirmed',
        ]);

        if (!Hash::check((string) $data['current_password'], (string) $user['password'])) {
            Redirect::back('/profile')
                ->withErrors(['current_password' => [__('auth.password_wrong', 'Password is incorrect.')]])
                ->send();
        }

        DB::table('users')->where('id', $user['id'])->update([
            'password' => Hash::make((string) $data['password']),
            'updated_at' => now(),
        ]);
        audit_log('profile.password_changed');
        Redirect::to('/profile')->with('success', __('profile.password_changed', 'Password changed.'))->send();
    }

    public function darkMode(Request $request): never
    {
        $user = Auth::user();
        DB::table('users')->where('id', $user['id'])->update(['dark_mode' => $request->bool('dark') ? 1 : 0]);
        $this->ok();
    }

    public function setLocale(Request $request): never
    {
        $locale = $request->str('locale');
        if (!array_key_exists($locale, Lang::available())) {
            $this->fail('Unknown language', 422);
        }
        Lang::setLocale($locale);
        DB::table('users')->where('id', Auth::id())->update(['locale' => $locale]);
        if ($request->wantsJson()) {
            $this->ok();
        }
        Redirect::back()->send();
    }
}
