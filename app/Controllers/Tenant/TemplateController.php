<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Queue;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\View;
use App\Services\Meta\TemplateService;

final class TemplateController extends Controller
{
    public function index(Request $request): never
    {
        $page = max(1, $request->int('page', 1));
        $result = DB::table('templates')
            ->where('tenant_id', Tenant::id())
            ->orderBy('id', 'DESC')
            ->paginate($page, 30);

        Layout::title(__('nav.templates', 'Templates'));
        View::render('tenant/templates/index', ['result' => $result], 'layouts/tenant');
    }

    public function create(Request $request): never
    {
        $wabas = DB::table('waba_accounts')->where('tenant_id', Tenant::id())->where('status', 'active')->get();
        Layout::title(__('templates.create', 'New template'));
        View::render('tenant/templates/create', ['wabas' => $wabas], 'layouts/tenant');
    }

    /**
     * Save a draft locally and submit it to Meta for approval.
     */
    public function store(Request $request): never
    {
        $data = $this->validate($request, [
            'waba_account_id' => 'required|integer',
            'name' => 'required|regex:/^[a-z0-9_]{1,512}$/',
            'language' => 'required|in:en,en_US,en_GB,gu,hi',
            'category' => 'required|in:MARKETING,UTILITY,AUTHENTICATION',
            'body' => 'required|string|max:1024',
            'header' => 'nullable|string|max:60',
            'footer' => 'nullable|string|max:60',
        ]);

        $waba = DB::table('waba_accounts')->where('id', (int) $data['waba_account_id'])
            ->where('tenant_id', Tenant::id())->where('status', 'active')->first();
        if ($waba === null) {
            Redirect::back('/tenant/templates/create')->with('error', __('whatsapp.no_number', 'No active WhatsApp number connected.'))->withInput()->send();
        }

        // Build Meta components
        $components = [];
        if (!empty($data['header'])) {
            $components[] = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => (string) $data['header']];
        }
        $components[] = ['type' => 'BODY', 'text' => (string) $data['body']];
        if (!empty($data['footer'])) {
            $components[] = ['type' => 'FOOTER', 'text' => (string) $data['footer']];
        }
        $buttons = [];
        foreach ($request->arr('buttons') as $button) {
            if (!is_array($button) || empty($button['text'])) {
                continue;
            }
            $type = (string) ($button['type'] ?? 'QUICK_REPLY');
            if ($type === 'URL' && !empty($button['url'])) {
                $buttons[] = ['type' => 'URL', 'text' => mb_substr((string) $button['text'], 0, 25), 'url' => (string) $button['url']];
            } elseif ($type === 'PHONE_NUMBER' && !empty($button['phone'])) {
                $buttons[] = ['type' => 'PHONE_NUMBER', 'text' => mb_substr((string) $button['text'], 0, 25), 'phone_number' => (string) $button['phone']];
            } else {
                $buttons[] = ['type' => 'QUICK_REPLY', 'text' => mb_substr((string) $button['text'], 0, 25)];
            }
        }
        if ($buttons) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => array_slice($buttons, 0, 10)];
        }

        $exists = DB::table('templates')->where('waba_account_id', $waba['id'])
            ->where('name', (string) $data['name'])->where('language', (string) $data['language'])->exists();
        if ($exists) {
            Redirect::back('/tenant/templates/create')->with('error', __('templates.duplicate', 'A template with this name and language already exists.'))->withInput()->send();
        }

        $id = DB::table('templates')->insert([
            'tenant_id' => (int) Tenant::id(),
            'waba_account_id' => (int) $waba['id'],
            'name' => (string) $data['name'],
            'language' => (string) $data['language'],
            'category' => (string) $data['category'],
            'status' => 'DRAFT',
            'components' => json_encode($components, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Submit to Meta
        try {
            $template = DB::table('templates')->where('id', $id)->first();
            TemplateService::submit($waba, $template);
            audit_log('templates.submitted', 'template', $id);
            Redirect::to('/tenant/templates')->with('success', __('templates.submitted', 'Template submitted to Meta for approval.'))->send();
        } catch (\Throwable $e) {
            Redirect::to('/tenant/templates')->with('warning', __('templates.saved_not_submitted', 'Saved as draft, but Meta submission failed: ') . $e->getMessage())->send();
        }
    }

    public function sync(Request $request): never
    {
        $wabas = DB::table('waba_accounts')->where('tenant_id', Tenant::id())->where('status', 'active')->get();
        if (empty($wabas)) {
            Redirect::back('/tenant/templates')->with('error', __('whatsapp.no_number', 'No active WhatsApp number connected.'))->send();
        }
        foreach ($wabas as $waba) {
            Queue::push(\App\Jobs\SyncTemplatesJob::class, ['waba_account_id' => (int) $waba['id']], 'default', 3, 0, (int) Tenant::id());
        }
        Redirect::to('/tenant/templates')->with('success', __('templates.sync_queued', 'Sync queued — templates refresh within a minute.'))->send();
    }

    public function destroy(Request $request): never
    {
        $template = DB::table('templates')
            ->where('id', (int) $request->route('id'))
            ->where('tenant_id', Tenant::id())
            ->first();
        if ($template === null) {
            Response::abort(404);
        }

        // Delete on Meta too (best-effort — template may only exist locally)
        try {
            $waba = DB::table('waba_accounts')->where('id', $template['waba_account_id'])->first();
            if ($waba !== null && !empty($template['meta_template_id'])) {
                $client = new \App\Services\Meta\CloudApiClient($waba);
                $client->deleteTemplate((string) $template['name']);
            }
        } catch (\Throwable) {
            // proceed with local delete regardless
        }

        DB::table('templates')->where('id', $template['id'])->delete();
        audit_log('templates.deleted', 'template', (int) $template['id'], ['name' => $template['name']]);
        Redirect::to('/tenant/templates')->with('success', __('templates.deleted', 'Template deleted.'))->send();
    }
}
