<?php

declare(strict_types=1);

namespace App\Controllers\PublicSite;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\Contact;

/**
 * /f/{tenant}/{slug} — public lead form renderer + submission handler.
 */
final class FormRendererController extends Controller
{
    public function show(Request $request): never
    {
        [$form, $fields] = $this->load($request);
        $settings = json_decode((string) ($form['settings'] ?? '{}'), true) ?: [];

        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $_SESSION['form_captcha'] = $a + $b;

        \App\Core\View::render('public/form', [
            'form' => $form,
            'fields' => $fields,
            'settings' => $settings,
            'captchaA' => $a,
            'captchaB' => $b,
            'action' => url('/f/' . $request->route('tenant') . '/' . $request->route('slug')),
        ]);
    }

    public function submit(Request $request): never
    {
        [$form, $fields] = $this->load($request);
        $tenantId = (int) $form['tenant_id'];

        if ($request->int('captcha', -1) !== (int) ($_SESSION['form_captcha'] ?? -2)) {
            \App\Core\Redirect::back()->with('error', __('forms.captcha_failed', 'Captcha answer is incorrect — try again.'))->withInput()->send();
        }

        $data = [];
        $phone = '';
        foreach ($fields as $field) {
            $value = $request->input('f_' . $field['key']);
            $value = is_scalar($value) ? Sanitizer::text((string) $value) : '';
            if ((int) $field['is_required'] === 1 && $value === '') {
                \App\Core\Redirect::back()->with('error', __('forms.required', ':f is required.', ['f' => (string) $field['label']]))->withInput()->send();
            }
            $data[$field['key']] = $value;
            if ($field['type'] === 'phone' || $field['key'] === 'phone') {
                $phone = Sanitizer::phone($value);
            }
        }

        \App\Core\Tenant::setId($tenantId);
        $contactId = null;
        $contact = null;
        if ($phone !== '') {
            $contact = Contact::firstOrCreateByPhone($phone, $data['name'] ?? null, 'form');
            $contactId = (int) $contact['id'];
            // Map form fields to contact custom fields where configured
            foreach ($fields as $field) {
                if (!empty($field['map_to_contact_field'])) {
                    $contactField = DB::table('contact_fields')->where('tenant_id', $tenantId)
                        ->where('key', (string) $field['map_to_contact_field'])->first();
                    if ($contactField !== null) {
                        Contact::setFieldValue($contactId, (int) $contactField['id'], $data[$field['key']] ?? null);
                    }
                }
            }
        }

        DB::table('form_submissions')->insert([
            'tenant_id' => $tenantId,
            'form_id' => (int) $form['id'],
            'contact_id' => $contactId,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
        DB::table('forms')->where('id', $form['id'])->increment('submission_count');

        // WhatsApp-on-submit: start flow or send template
        if ($contact !== null) {
            if (!empty($form['flow_id'])) {
                $flow = DB::table('flows')->where('id', $form['flow_id'])->where('status', 'active')->first();
                if ($flow !== null) {
                    try {
                        \App\Services\Automation\FlowEngine::start($flow, $contact, null, ['form' => $data, 'trigger' => ['type' => 'form_submit']]);
                    } catch (\Throwable) {
                        // form must still succeed for the visitor
                    }
                }
            }
        }

        \App\Core\Redirect::back()->with('success', (string) (json_decode((string) $form['settings'], true)['thank_you'] ?? __('forms.thanks', 'Thank you! We will contact you on WhatsApp shortly.')))->send();
    }

    private function load(Request $request): array
    {
        $tenant = DB::table('tenants')->where('slug', (string) $request->route('tenant'))->where('status', 'active')->first();
        if ($tenant === null) {
            Response::abort(404);
        }
        $form = DB::table('forms')
            ->where('tenant_id', $tenant['id'])
            ->where('slug', (string) $request->route('slug'))
            ->where('is_active', 1)
            ->first();
        if ($form === null) {
            Response::abort(404);
        }
        $fields = DB::table('form_fields')->where('form_id', $form['id'])->orderBy('sort_order')->get();
        return [$form, $fields];
    }
}
