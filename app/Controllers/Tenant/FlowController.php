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

final class FlowController extends Controller
{
    public function index(Request $request): never
    {
        $flows = DB::table('flows')->where('tenant_id', Tenant::id())->orderBy('id', 'DESC')->get();
        Layout::title(__('nav.flows', 'Bot Flows'));
        View::render('tenant/flows/index', ['flows' => $flows], 'layouts/tenant');
    }

    public function store(Request $request): never
    {
        [$allowed] = Tenant::withinLimit('bot_flows');
        if (!$allowed) {
            Redirect::back('/tenant/flows')->with('error', __('billing.flow_limit', 'Bot flow limit reached. Upgrade your plan.'))->send();
        }

        $data = $this->validate($request, [
            'name' => 'required|string|max:191',
            'trigger_type' => 'required|in:keyword,any_message,new_contact,schedule,api,form_submit,qr_scan,order_event',
            'keyword' => 'nullable|string|max:191',
        ]);

        // Sensible starter definition: welcome message flow
        $definition = [
            'start' => 'n1',
            'nodes' => [
                'n1' => ['type' => 'start', 'next' => 'n2'],
                'n2' => ['type' => 'send_message', 'config' => ['text' => __('flows.default_reply', 'Hello {{contact.name}}! 👋 How can we help you today?')], 'next' => null],
            ],
        ];

        $id = DB::table('flows')->insert([
            'tenant_id' => (int) Tenant::id(),
            'name' => (string) $data['name'],
            'definition' => json_encode($definition, JSON_UNESCAPED_UNICODE),
            'status' => 'draft',
            'trigger_type' => (string) $data['trigger_type'],
            'trigger_config' => json_encode(['keyword' => $data['keyword'] ?? null]),
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Keyword triggers register a keywords row for the matcher
        if ($data['trigger_type'] === 'keyword' && !empty($data['keyword'])) {
            DB::table('keywords')->insert([
                'tenant_id' => (int) Tenant::id(),
                'keyword' => (string) $data['keyword'],
                'match_type' => 'exact',
                'flow_id' => $id,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Tenant::recordUsage('bot_flows');
        audit_log('flows.created', 'flow', $id);
        Redirect::to('/tenant/flows/' . $id . '/edit')->with('success', __('flows.created', 'Flow created — design it below.'))->send();
    }

    public function edit(Request $request): never
    {
        $flow = $this->findFlow($request);
        Layout::title($flow['name']);
        View::render('tenant/flows/edit', [
            'flow' => $flow,
            'templates' => DB::table('templates')->where('tenant_id', Tenant::id())->where('status', 'APPROVED')->get(),
            'tags' => DB::table('tags')->where('tenant_id', Tenant::id())->get(),
            'runs' => DB::table('flow_runs')->where('flow_id', $flow['id'])->orderBy('id', 'DESC')->limit(10)->get(),
        ], 'layouts/tenant');
    }

    /**
     * Save the flow definition (JSON graph from the editor).
     */
    public function update(Request $request): never
    {
        $flow = $this->findFlow($request);
        $definitionRaw = $request->str('definition');
        $definition = json_decode($definitionRaw, true);
        if (!is_array($definition) || empty($definition['nodes']) || empty($definition['start'])) {
            $this->fail(__('flows.invalid_definition', 'Flow definition is invalid: it needs a start node and at least one node.'), 422);
        }
        if (!isset($definition['nodes'][$definition['start']])) {
            $this->fail(__('flows.invalid_start', 'The start node reference does not exist.'), 422);
        }

        // Version history snapshot
        DB::table('flow_versions')->insert([
            'flow_id' => (int) $flow['id'],
            'version' => (int) $flow['version'],
            'definition' => (string) $flow['definition'],
            'created_by' => Auth::id(),
            'created_at' => now(),
        ]);

        DB::table('flows')->where('id', $flow['id'])->update([
            'definition' => json_encode($definition, JSON_UNESCAPED_UNICODE),
            'version' => (int) $flow['version'] + 1,
            'updated_at' => now(),
        ]);

        audit_log('flows.updated', 'flow', (int) $flow['id']);
        $this->ok(['version' => (int) $flow['version'] + 1]);
    }

    public function toggle(Request $request): never
    {
        $flow = $this->findFlow($request);
        $newStatus = $flow['status'] === 'active' ? 'paused' : 'active';
        DB::table('flows')->where('id', $flow['id'])->update(['status' => $newStatus, 'updated_at' => now()]);
        audit_log('flows.' . $newStatus, 'flow', (int) $flow['id']);
        Redirect::back('/tenant/flows')->with('success', $newStatus === 'active'
            ? __('flows.activated', 'Flow activated.')
            : __('flows.paused', 'Flow paused.'))->send();
    }

    public function destroy(Request $request): never
    {
        $flow = $this->findFlow($request);
        DB::table('keywords')->where('flow_id', $flow['id'])->delete();
        DB::table('flows')->where('id', $flow['id'])->delete();
        audit_log('flows.deleted', 'flow', (int) $flow['id'], ['name' => $flow['name']]);
        Redirect::to('/tenant/flows')->with('success', __('flows.deleted', 'Flow deleted.'))->send();
    }

    private function findFlow(Request $request): array
    {
        $flow = DB::table('flows')
            ->where('id', (int) $request->route('id'))
            ->where('tenant_id', Tenant::id())
            ->first();
        if ($flow === null) {
            Response::abort(404);
        }
        return $flow;
    }
}
