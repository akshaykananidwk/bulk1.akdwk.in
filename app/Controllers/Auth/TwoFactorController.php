<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypt;
use App\Core\DB;
use App\Core\Hash;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\View;

/**
 * TOTP two-factor authentication (RFC 6238, pure PHP) + backup codes.
 */
final class TwoFactorController extends Controller
{
    public function show(Request $request): never
    {
        if (empty($_SESSION['2fa_pending_user_id'])) {
            Redirect::to('/login')->send();
        }
        View::render('auth/twofactor', [], 'layouts/auth');
    }

    public function verify(Request $request): never
    {
        $userId = (int) ($_SESSION['2fa_pending_user_id'] ?? 0);
        if ($userId <= 0) {
            Redirect::to('/login')->send();
        }

        $user = DB::table('users')->where('id', $userId)->first();
        if ($user === null) {
            Redirect::to('/login')->send();
        }

        $code = preg_replace('/\s+/', '', $request->str('code'));
        $valid = false;

        // TOTP
        if (!empty($user['two_factor_secret'])) {
            $secret = Crypt::decrypt((string) $user['two_factor_secret']);
            if ($secret !== null && self::verifyTotp($secret, (string) $code)) {
                $valid = true;
            }
        }

        // Backup code fallback
        if (!$valid && !empty($user['two_factor_backup_codes'])) {
            $codes = json_decode((string) $user['two_factor_backup_codes'], true) ?: [];
            foreach ($codes as $i => $hash) {
                if (password_verify((string) $code, (string) $hash)) {
                    unset($codes[$i]);
                    DB::table('users')->where('id', $userId)->update([
                        'two_factor_backup_codes' => json_encode(array_values($codes)),
                    ]);
                    $valid = true;
                    break;
                }
            }
        }

        if (!$valid) {
            Redirect::back('/2fa')
                ->withErrors(['code' => [__('auth.2fa_invalid', 'Invalid code. Try again.')]])
                ->send();
        }

        $remember = (bool) ($_SESSION['2fa_remember'] ?? false);
        Auth::login($user, $remember);
        audit_log('auth.2fa_success', 'user', $userId);
        Redirect::to(Auth::isSuperAdmin() ? '/admin' : '/tenant')->send();
    }

    /**
     * Step 1 of enabling: generate secret, return otpauth URI + QR data.
     */
    public function enable(Request $request): never
    {
        $user = Auth::user();
        if ($user === null) {
            $this->fail('Unauthenticated', 401);
        }

        $secret = self::generateSecret();
        $_SESSION['2fa_setup_secret'] = $secret;

        $issuer = rawurlencode((string) setting('app_name', 'Krishna WhatsApp Cloud'));
        $label = rawurlencode((string) $user['email']);
        $uri = "otpauth://totp/{$issuer}:{$label}?secret={$secret}&issuer={$issuer}&digits=6&period=30";

        $this->ok(['secret' => $secret, 'uri' => $uri]);
    }

    /**
     * Step 2: confirm with a valid code; store encrypted + backup codes.
     */
    public function confirm(Request $request): never
    {
        $user = Auth::user();
        $secret = (string) ($_SESSION['2fa_setup_secret'] ?? '');
        if ($user === null || $secret === '') {
            $this->fail(__('auth.2fa_setup_expired', 'Setup expired — start again.'), 422);
        }

        if (!self::verifyTotp($secret, $request->str('code'))) {
            $this->fail(__('auth.2fa_invalid', 'Invalid code. Try again.'), 422);
        }

        $backupCodes = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $plain = strtoupper(bin2hex(random_bytes(4)));
            $backupCodes[] = $plain;
            $hashes[] = password_hash($plain, PASSWORD_BCRYPT);
        }

        DB::table('users')->where('id', $user['id'])->update([
            'two_factor_secret' => Crypt::encrypt($secret),
            'two_factor_backup_codes' => json_encode($hashes),
            'updated_at' => now(),
        ]);
        unset($_SESSION['2fa_setup_secret']);
        audit_log('auth.2fa_enabled');

        $this->ok(['backup_codes' => $backupCodes], __('auth.2fa_enabled', 'Two-factor authentication enabled.'));
    }

    public function disable(Request $request): never
    {
        $user = Auth::user();
        if ($user === null || !\App\Core\Hash::check($request->str('password'), (string) $user['password'])) {
            $this->fail(__('auth.password_wrong', 'Password is incorrect.'), 422);
        }
        DB::table('users')->where('id', $user['id'])->update([
            'two_factor_secret' => null,
            'two_factor_backup_codes' => null,
            'updated_at' => now(),
        ]);
        audit_log('auth.2fa_disabled');
        $this->ok(null, __('auth.2fa_disabled', 'Two-factor authentication disabled.'));
    }

    // -- TOTP internals --------------------------------------------------------

    public static function generateSecret(int $length = 32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    public static function verifyTotp(string $secret, string $code, int $window = 1): bool
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $timeSlice = (int) floor(time() / 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::totpAt($secret, $timeSlice + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    private static function totpAt(string $secret, int $timeSlice): string
    {
        $key = self::base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;
        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $input) ?? '');
        $binary = '';
        foreach (str_split($input) as $char) {
            $binary .= str_pad(decbin((int) strpos($alphabet, $char)), 5, '0', STR_PAD_LEFT);
        }
        $output = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $output .= chr((int) bindec($byte));
            }
        }
        return $output;
    }
}
