<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Hash;
use App\Core\Mail;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\View;

final class ForgotPasswordController extends Controller
{
    public function show(Request $request): never
    {
        View::render('auth/forgot', [], 'layouts/auth');
    }

    public function send(Request $request): never
    {
        $data = $this->validate($request, ['email' => 'required|email']);
        $email = strtolower((string) $data['email']);

        $user = DB::table('users')->where('email', $email)->first();
        if ($user !== null) {
            $token = Hash::token(32);
            // Reuse user_sessions with type api as a reset-token store (selector = reset:<token-hash prefix>)
            DB::table('user_sessions')->where('user_id', $user['id'])->whereLike('selector', 'pw%')->delete();
            DB::table('user_sessions')->insert([
                'user_id' => (int) $user['id'],
                'selector' => 'pw' . substr(hash('sha256', $token), 0, 24),
                'token_hash' => hash('sha256', $token),
                'type' => 'api',
                'ip' => $request->ip(),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'created_at' => now(),
            ]);

            Mail::make()
                ->to($email, (string) $user['name'])
                ->subject(__('mail.reset_subject', 'Reset your password'))
                ->html(
                    '<p>' . e(__('mail.reset_intro', 'Click the link below to reset your password. The link is valid for 1 hour.')) . '</p>'
                    . '<p><a href="' . e(url('/reset-password/' . $token)) . '">' . e(url('/reset-password/' . $token)) . '</a></p>'
                )
                ->send();
        }

        // Same response either way — do not leak account existence
        Redirect::to('/login')->with('success', __('auth.reset_sent', 'If that email exists, a reset link has been sent.'))->send();
    }

    public function showReset(Request $request): never
    {
        $token = (string) $request->route('token');
        View::render('auth/reset', ['token' => $token], 'layouts/auth');
    }

    public function reset(Request $request): never
    {
        $data = $this->validate($request, [
            'token' => 'required|string',
            'password' => 'required|password_strength|confirmed',
        ]);

        $token = (string) $data['token'];
        $row = DB::table('user_sessions')
            ->where('selector', 'pw' . substr(hash('sha256', $token), 0, 24))
            ->where('expires_at', '>', now())
            ->first();

        if ($row === null || !hash_equals((string) $row['token_hash'], hash('sha256', $token))) {
            Redirect::to('/forgot-password')
                ->with('error', __('auth.reset_invalid', 'This reset link is invalid or expired.'))
                ->send();
        }

        DB::table('users')->where('id', $row['user_id'])->update([
            'password' => \App\Core\Hash::make((string) $data['password']),
            'updated_at' => now(),
        ]);
        DB::table('user_sessions')->where('user_id', $row['user_id'])->delete();
        audit_log('auth.password_reset', 'user', (int) $row['user_id']);

        Redirect::to('/login')->with('success', __('auth.reset_done', 'Password updated. Please log in.'))->send();
    }
}
