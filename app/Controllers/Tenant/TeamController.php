<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Hash;
use App\Core\Layout;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\View;
use App\Models\User;

final class TeamController extends Controller
{
    public function index(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        $members = DB::table('users u')
            ->leftJoin('roles', 'u.role_id', '=', 'roles.id')
            ->leftJoin('agent_presence', 'u.id', '=', 'agent_presence.user_id')
            ->where('u.tenant_id', $tenantId)
            ->select('u.id', 'u.name', 'u.email', 'u.status', 'u.last_login_at',
                'roles.name AS role_name', 'roles.slug AS role_slug', 'agent_presence.status AS presence')
            ->orderBy('u.name')->get();

        Layout::title(__('nav.team', 'Team'));
        View::render('tenant/team', [
            'members' => $members,
            'roles' => DB::table('roles')->where('tenant_id', $tenantId)->orderBy('name')->get(),
        ], 'layouts/tenant');
    }

    public function invite(Request $request): never
    {
        [$allowed] = Tenant::withinLimit('agents');
        if (!$allowed) {
            Redirect::back('/tenant/team')->with('error', __('billing.agent_limit', 'Agent limit reached. Upgrade your plan.'))->send();
        }

        $data = $this->validate($request, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|unique:users,email',
            'role_id' => 'required|integer',
        ]);

        $role = DB::table('roles')->where('id', (int) $data['role_id'])->where('tenant_id', Tenant::id())->first();
        if ($role === null) {
            Redirect::back('/tenant/team')->with('error', __('team.role_invalid', 'Invalid role.'))->send();
        }

        // Temporary password, sent by email; user changes it via reset flow
        $temporaryPassword = Hash::token(9);
        $userId = User::register((int) Tenant::id(), (string) $data['name'], (string) $data['email'], $temporaryPassword, (int) $role['id']);

        \App\Core\Queue::push(\App\Jobs\SendMailJob::class, [
            'to' => strtolower((string) $data['email']),
            'to_name' => (string) $data['name'],
            'subject' => __('team.invite_subject', 'You have been invited to :app', ['app' => (string) setting('app_name', 'Krishna WhatsApp Cloud')]),
            'html' => '<p>' . e(__('team.invite_body', 'You have been added to the team. Log in with:')) . '</p>'
                . '<p><strong>' . e(url('/login')) . '</strong><br>Email: ' . e((string) $data['email'])
                . '<br>' . e(__('team.temp_password', 'Temporary password')) . ': <code>' . e($temporaryPassword) . '</code></p>'
                . '<p>' . e(__('team.change_password', 'Change your password after your first login.')) . '</p>',
        ], 'mail', 5, 0, (int) Tenant::id());

        Tenant::recordUsage('agents');
        audit_log('team.invited', 'user', $userId, ['email' => $data['email']]);
        Redirect::to('/tenant/team')->with('success', __('team.invited', 'Team member added — login details emailed.'))->send();
    }

    public function update(Request $request): never
    {
        $member = $this->findMember($request);
        $data = $this->validate($request, [
            'role_id' => 'nullable|integer',
            'status' => 'nullable|in:active,inactive',
        ]);

        $update = ['updated_at' => now()];
        if (!empty($data['role_id'])) {
            $role = DB::table('roles')->where('id', (int) $data['role_id'])->where('tenant_id', Tenant::id())->first();
            if ($role !== null) {
                $update['role_id'] = (int) $role['id'];
            }
        }
        if (!empty($data['status']) && (int) $member['id'] !== (int) Auth::id()) {
            $update['status'] = (string) $data['status'];
        }

        DB::table('users')->where('id', $member['id'])->update($update);
        audit_log('team.updated', 'user', (int) $member['id']);
        Redirect::to('/tenant/team')->with('success', __('team.updated', 'Member updated.'))->send();
    }

    public function remove(Request $request): never
    {
        $member = $this->findMember($request);
        if ((int) $member['id'] === (int) Auth::id()) {
            Redirect::back('/tenant/team')->with('error', __('team.cannot_remove_self', 'You cannot remove yourself.'))->send();
        }
        // Unassign their conversations, then remove
        DB::table('conversations')->where('assigned_to', $member['id'])->update(['assigned_to' => null]);
        DB::table('users')->where('id', $member['id'])->delete();
        audit_log('team.removed', 'user', (int) $member['id'], ['email' => $member['email']]);
        Redirect::to('/tenant/team')->with('success', __('team.removed', 'Member removed.'))->send();
    }

    private function findMember(Request $request): array
    {
        $member = DB::table('users')
            ->where('id', (int) $request->route('id'))
            ->where('tenant_id', Tenant::id())
            ->first();
        if ($member === null) {
            Response::abort(404);
        }
        return $member;
    }
}
