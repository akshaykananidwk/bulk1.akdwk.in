<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Layout;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\View;
use App\Services\Campaign\CampaignBuilder;

final class CampaignController extends Controller
{
    public function index(Request $request): never
    {
        $page = max(1, $request->int('page', 1));
        $result = DB::table('campaigns')
            ->where('tenant_id', Tenant::id())
            ->orderBy('id', 'DESC')
            ->paginate($page, 20);

        Layout::title(__('nav.campaigns', 'Campaigns'));
        View::render('tenant/campaigns/index', ['result' => $result], 'layouts/tenant');
    }

    public function create(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        Layout::title(__('campaigns.create', 'New campaign'));
        View::render('tenant/campaigns/create', [
            'templates' => DB::table('templates')->where('tenant_id', $tenantId)->where('status', 'APPROVED')->orderBy('name')->get(),
            'groups' => DB::table('groups')->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'tags' => DB::table('tags')->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'segments' => DB::table('segments')->where('tenant_id', $tenantId)->orderBy('name')->get(),
            'numbers' => DB::table('phone_numbers')->where('tenant_id', $tenantId)->where('status', 'active')->get(),
        ], 'layouts/tenant');
    }

    public function store(Request $request): never
    {
        [$allowed] = Tenant::withinLimit('campaigns_monthly');
        if (!$allowed) {
            Redirect::back('/tenant/campaigns/create')->with('error', __('billing.campaign_limit', 'Monthly campaign limit reached. Upgrade your plan.'))->withInput()->send();
        }

        $data = $this->validate($request, [
            'name' => 'required|string|max:191',
            'template_id' => 'required|integer',
            'audience_type' => 'required|in:all,groups,segments,tags,manual',
            'throttle_per_minute' => 'required|integer|between:1,1000',
            'scheduled_at' => 'nullable|string',
        ]);

        $template = DB::table('templates')->where('id', (int) $data['template_id'])
            ->where('tenant_id', Tenant::id())->where('status', 'APPROVED')->first();
        if ($template === null) {
            Redirect::back('/tenant/campaigns/create')->with('error', __('templates.not_found', 'Template not found or not approved.'))->withInput()->send();
        }

        $scheduledAt = null;
        if (!empty($data['scheduled_at'])) {
            $timestamp = strtotime((string) $data['scheduled_at']);
            if ($timestamp !== false && $timestamp > time()) {
                $scheduledAt = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $audienceConfig = [
            'group_ids' => array_map('intval', $request->arr('group_ids')),
            'tag_ids' => array_map('intval', $request->arr('tag_ids')),
            'segment_ids' => array_map('intval', $request->arr('segment_ids')),
        ];

        // Variable mapping: variables[n][source|value]
        $mapping = [];
        foreach ($request->arr('variables') as $key => $map) {
            if (is_array($map)) {
                $mapping[(string) $key] = [
                    'source' => (string) ($map['source'] ?? 'static'),
                    'value' => (string) ($map['value'] ?? ''),
                ];
            }
        }

        $id = DB::table('campaigns')->insert([
            'tenant_id' => (int) Tenant::id(),
            'name' => (string) $data['name'],
            'phone_number_id' => $request->int('phone_number_id') ?: null,
            'template_id' => (int) $template['id'],
            'message_type' => 'template',
            'variable_mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE),
            'audience_type' => (string) $data['audience_type'],
            'audience_config' => json_encode($audienceConfig),
            'status' => 'draft',
            'scheduled_at' => $scheduledAt,
            'throttle_per_minute' => (int) $data['throttle_per_minute'],
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Tenant::recordUsage('campaigns_monthly');
        audit_log('campaigns.created', 'campaign', $id);
        Redirect::to('/tenant/campaigns/' . $id)->with('success', __('campaigns.created', 'Campaign created. Review the audience, then launch.'))->send();
    }

    public function show(Request $request): never
    {
        $campaign = $this->findCampaign($request);
        $template = $campaign['template_id'] ? DB::table('templates')->where('id', $campaign['template_id'])->first() : null;

        $statusCounts = [];
        $rows = DB::table('campaign_recipients')
            ->selectRaw(DB::raw('`status`, COUNT(*) AS n'))
            ->where('campaign_id', $campaign['id'])
            ->groupBy('status')->get();
        foreach ($rows as $row) {
            $statusCounts[$row['status']] = (int) $row['n'];
        }

        Layout::title($campaign['name']);
        View::render('tenant/campaigns/show', [
            'campaign' => $campaign,
            'template' => $template,
            'statusCounts' => $statusCounts,
            'recentFailures' => DB::table('campaign_recipients cr')
                ->join('contacts', 'cr.contact_id', '=', 'contacts.id')
                ->where('cr.campaign_id', $campaign['id'])
                ->where('cr.status', 'failed')
                ->select('contacts.phone', 'contacts.name', 'cr.error')
                ->limit(20)->get(),
        ], 'layouts/tenant');
    }

    public function launch(Request $request): never
    {
        $campaign = $this->findCampaign($request);
        try {
            CampaignBuilder::launch($campaign);
        } catch (\Throwable $e) {
            Redirect::back('/tenant/campaigns/' . $campaign['id'])->with('error', $e->getMessage())->send();
        }
        Redirect::to('/tenant/campaigns/' . $campaign['id'])->with('success', __('campaigns.launched', 'Campaign launched!'))->send();
    }

    public function pause(Request $request): never
    {
        $this->controlAction($request, 'pause');
    }

    public function resume(Request $request): never
    {
        $this->controlAction($request, 'resume');
    }

    public function cancel(Request $request): never
    {
        $this->controlAction($request, 'cancel');
    }

    public function progress(Request $request): never
    {
        $campaign = $this->findCampaign($request);
        $this->json([
            'status' => $campaign['status'],
            'total' => (int) $campaign['total_recipients'],
            'sent' => (int) $campaign['sent_count'],
            'delivered' => (int) $campaign['delivered_count'],
            'read' => (int) $campaign['read_count'],
            'failed' => (int) $campaign['failed_count'],
            'cost' => (float) $campaign['total_cost'],
        ]);
    }

    private function controlAction(Request $request, string $action): never
    {
        $campaign = $this->findCampaign($request);
        try {
            CampaignBuilder::control($campaign, $action);
        } catch (\Throwable $e) {
            Redirect::back('/tenant/campaigns/' . $campaign['id'])->with('error', $e->getMessage())->send();
        }
        Redirect::to('/tenant/campaigns/' . $campaign['id'])->with('success', __('campaigns.' . $action . '_done', ucfirst($action) . 'd.'))->send();
    }

    private function findCampaign(Request $request): array
    {
        $campaign = DB::table('campaigns')
            ->where('id', (int) $request->route('id'))
            ->where('tenant_id', Tenant::id())
            ->first();
        if ($campaign === null) {
            Response::abort(404);
        }
        return $campaign;
    }
}
