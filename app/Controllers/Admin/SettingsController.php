<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Crypt;
use App\Core\Layout;
use App\Core\Mail;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\View;

final class SettingsController extends Controller
{
    /** Settings editable here, grouped for the UI. Secrets are encrypted. */
    private const GROUPS = [
        'branding' => ['app_name', 'app_tagline', 'default_language', 'default_currency', 'date_format'],
        'registration' => ['registration_enabled', 'email_verification_required', 'default_plan_slug'],
        'mail' => ['mail_driver', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'mail_from_email', 'mail_from_name'],
        'alerts' => ['alert_email', 'queue_backlog_alert'],
        'security' => ['admin_ip_allowlist', 'captcha_provider'],
        'system' => ['worker_mode', 'backup_daily'],
    ];

    private const SECRETS = ['mail_password'];

    public function index(Request $request): never
    {
        $values = [];
        foreach (self::GROUPS as $keys) {
            foreach ($keys as $key) {
                $value = (string) setting($key, '');
                if (in_array($key, self::SECRETS, true) && $value !== '') {
                    $value = '••••••••';
                }
                $values[$key] = $value;
            }
        }

        Layout::title(__('admin.settings', 'Global Settings'));
        View::render('admin/settings', ['values' => $values], 'layouts/admin');
    }

    public function update(Request $request): never
    {
        foreach (self::GROUPS as $keys) {
            foreach ($keys as $key) {
                $value = $request->input($key);
                if ($value === null) {
                    continue;
                }
                $value = is_scalar($value) ? trim((string) $value) : '';
                if (in_array($key, self::SECRETS, true)) {
                    if ($value === '' || str_contains($value, '•')) {
                        continue; // unchanged
                    }
                    $value = Crypt::encrypt($value);
                }
                set_setting($key, $value);
            }
        }

        // Optional SMTP test — the EXACT failure reason is shown, never hidden
        if ($request->bool('send_test_mail')) {
            $to = (string) setting('alert_email', '');
            if ($to === '') {
                Redirect::to('/admin/settings')->with('warning', __('admin.test_mail_no_address', 'Settings saved, but no Alert email is set — enter it in the Alerts section first, then re-test.'))->send();
            }
            $sent = Mail::make()->to($to)->subject('[Test] ' . setting('app_name', 'Krishna WhatsApp Cloud') . ' mail settings')
                ->text('Mail configuration works! — ' . setting('app_name', 'Krishna WhatsApp Cloud'))->send();
            if (!$sent) {
                Redirect::to('/admin/settings')->with('error', __('admin.smtp_test_failed', 'Settings saved, but the test email FAILED: ')
                    . (Mail::$lastError ?? __('admin.unknown_error', 'unknown error — see storage/logs/mail-*.log')))->send();
            }
            Redirect::to('/admin/settings')->with('success', __('admin.test_mail_ok', 'Settings saved and test email sent to :to — inbox (and spam folder) check karo.', ['to' => $to]))->send();
        }

        audit_log('admin.settings_updated');
        Redirect::to('/admin/settings')->with('success', __('admin.settings_saved', 'Settings saved.'))->send();
    }
}
