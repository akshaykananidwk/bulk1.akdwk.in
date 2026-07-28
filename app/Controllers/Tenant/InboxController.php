<?php

declare(strict_types=1);

namespace App\Controllers\Tenant;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Event;
use App\Core\Layout;
use App\Core\Request;
use App\Core\Response;
use App\Core\Storage;
use App\Core\Tenant;
use App\Core\View;
use App\Models\User;
use App\Services\Meta\CloudApiClient;
use App\Services\Meta\MediaService;
use App\Services\Meta\MessageSender;
use App\Services\Meta\SessionWindowService;
use App\Services\Meta\TemplateService;

/**
 * Team inbox: three-pane chat UI + JSON endpoints for the live client.
 */
final class InboxController extends Controller
{
    public function index(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        Layout::title(__('inbox.title', 'Team Inbox'));

        View::render('tenant/inbox', [
            'agents' => User::agentsOf($tenantId),
            'quickReplies' => DB::table('quick_replies')->where('tenant_id', $tenantId)->orderBy('shortcut')->get(),
            'templates' => DB::table('templates')->where('tenant_id', $tenantId)->where('status', 'APPROVED')->orderBy('name')->get(),
        ], 'layouts/tenant');
    }

    /**
     * Conversation list (JSON) with filters + cursor pagination.
     */
    public function list(Request $request): never
    {
        $tenantId = (int) Tenant::id();
        $filter = $request->str('filter', 'all'); // all|mine|unassigned|starred|archived|resolved
        $search = $request->str('q');
        $before = $request->str('before'); // cursor: last_message_at of last item

        $query = DB::table('conversations c')
            ->join('contacts', 'c.contact_id', '=', 'contacts.id')
            ->where('c.tenant_id', $tenantId)
            ->select(
                'c.id', 'c.status', 'c.priority', 'c.unread_count', 'c.assigned_to',
                'c.is_pinned', 'c.is_starred', 'c.is_archived',
                'c.last_message_preview', 'c.last_message_at', 'c.session_expires_at', 'c.sentiment',
                'contacts.name', 'contacts.phone', 'contacts.avatar'
            );

        match ($filter) {
            'mine' => $query->where('c.assigned_to', Auth::id())->where('c.is_archived', 0),
            'unassigned' => $query->whereNull('c.assigned_to')->where('c.is_archived', 0)->whereIn('c.status', ['open', 'pending']),
            'starred' => $query->where('c.is_starred', 1)->where('c.is_archived', 0),
            'archived' => $query->where('c.is_archived', 1),
            'resolved' => $query->where('c.status', 'resolved')->where('c.is_archived', 0),
            default => $query->where('c.is_archived', 0),
        };

        // Agents without view_all only see their own + unassigned
        if (!Auth::can('inbox.view_all')) {
            $query->whereGroup(function ($q) {
                $q->where('c.assigned_to', Auth::id())->orWhere('c.assigned_to', '=', null);
            });
        }

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->whereGroup(function ($q) use ($like) {
                $q->whereLike('contacts.name', $like)->whereLike('contacts.phone', $like, 'OR');
            });
        }

        if ($before !== '') {
            $query->where('c.last_message_at', '<', $before);
        }

        $rows = $query->orderBy('c.is_pinned', 'DESC')->orderBy('c.last_message_at', 'DESC')->limit(30)->get();

        foreach ($rows as &$row) {
            $row['window_open'] = $row['session_expires_at'] !== null && strtotime((string) $row['session_expires_at']) > time();
            $row['window_seconds'] = $row['window_open'] ? strtotime((string) $row['session_expires_at']) - time() : 0;
            $row['time_label'] = $row['last_message_at'] ? \App\Core\DateHelper::chatStamp((string) $row['last_message_at']) : '';
        }
        unset($row);

        $this->json(['conversations' => $rows]);
    }

    public function show(Request $request): never
    {
        $conversation = $this->findConversation($request);
        $contact = DB::table('contacts')->where('id', $conversation['contact_id'])->first();
        $tags = DB::table('contact_tags')
            ->join('tags', 'contact_tags.tag_id', '=', 'tags.id')
            ->where('contact_tags.contact_id', $conversation['contact_id'])
            ->select('tags.id', 'tags.name', 'tags.color')->get();
        $notes = DB::table('conversation_notes cn')
            ->join('users', 'cn.user_id', '=', 'users.id')
            ->where('cn.conversation_id', $conversation['id'])
            ->select('cn.id', 'cn.body', 'cn.created_at', 'users.name')
            ->orderBy('cn.id', 'DESC')->limit(20)->get();

        $this->json([
            'conversation' => $conversation + [
                'window_open' => SessionWindowService::isOpen($conversation),
                'window_seconds' => SessionWindowService::secondsRemaining($conversation),
            ],
            'contact' => $contact,
            'tags' => $tags,
            'notes' => $notes,
        ]);
    }

    /**
     * Messages with cursor pagination (infinite scroll upwards).
     */
    public function messages(Request $request): never
    {
        $conversation = $this->findConversation($request);
        $beforeId = $request->int('before_id');

        $query = DB::table('messages')
            ->where('conversation_id', $conversation['id'])
            ->select('id', 'wamid', 'direction', 'type', 'body', 'media_path', 'media_mime',
                'status', 'error_code', 'error_title', 'context_wamid', 'is_private_note', 'user_id', 'created_at');
        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }
        $rows = $query->orderBy('id', 'DESC')->limit(50)->get();
        $rows = array_reverse($rows);

        foreach ($rows as &$row) {
            $row['media_url'] = $row['media_path'] ? url('/media/' . $row['media_path']) : null;
            $row['time_label'] = \App\Core\DateHelper::display((string) $row['created_at'], 'h:i A');
            $row['day'] = date('Y-m-d', strtotime((string) $row['created_at']));
        }
        unset($row);

        $this->json(['messages' => $rows]);
    }

    /**
     * Send from the composer: text, media upload, template, or reply.
     */
    public function send(Request $request): never
    {
        $conversation = $this->findConversation($request);
        $kind = $request->str('kind', 'text'); // text|media|template

        try {
            if ($kind === 'template') {
                $templateId = $request->int('template_id');
                $template = DB::table('templates')->where('id', $templateId)
                    ->where('tenant_id', Tenant::id())->where('status', 'APPROVED')->first();
                if ($template === null) {
                    $this->fail(__('templates.not_found', 'Template not found or not approved.'), 422);
                }
                $contact = DB::table('contacts')->where('id', $conversation['contact_id'])->first() ?? [];
                $mapping = $request->arr('variables');
                $components = TemplateService::buildSendComponents($template, $mapping, $contact, \App\Models\Contact::fieldValues((int) $contact['id']));
                $message = MessageSender::send($conversation, 'template', [
                    'name' => $template['name'],
                    'language' => $template['language'],
                    'components' => $components,
                ], [
                    'user_id' => Auth::id(),
                    'template_category' => strtolower((string) $template['category']),
                ]);
            } elseif ($kind === 'media') {
                $file = $request->file('file');
                if ($file === null) {
                    $this->fail(__('upload.failed', 'Upload failed. Please try again.'), 422);
                }
                $relative = Storage::putUpload($file, 'media', [
                    'jpg', 'jpeg', 'png', 'webp', 'mp4', '3gp', 'mp3', 'aac', 'ogg', 'opus', 'amr', 'wav',
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv',
                ], 100 * 1024 * 1024);
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = (string) $finfo->file(Storage::path($relative));

                $phoneNumber = DB::table('phone_numbers')->where('id', $conversation['phone_number_id'])->first()
                    ?? DB::table('phone_numbers')->where('tenant_id', Tenant::id())->where('status', 'active')->orderBy('is_default', 'DESC')->first();
                if ($phoneNumber === null) {
                    $this->fail(__('whatsapp.no_number', 'No active WhatsApp number connected.'), 422);
                }
                $upload = MediaService::uploadForSending($phoneNumber, $relative, $mime);
                $message = MessageSender::send($conversation, $upload['type'], [
                    'media_id' => $upload['media_id'],
                    'caption' => $request->str('caption'),
                    'filename' => basename((string) $file['name']),
                    'media_path' => $relative,
                    'media_mime' => $mime,
                ], ['user_id' => Auth::id()]);
            } else {
                $body = $request->str('body');
                if ($body === '') {
                    $this->fail(__('inbox.empty_message', 'Type a message first.'), 422);
                }
                // Auto-append agent signature if configured
                $signature = DB::table('agent_signatures')->where('user_id', Auth::id())->first();
                if ($signature !== null && (int) $signature['auto_append'] === 1) {
                    $body .= "\n\n" . $signature['signature'];
                }
                $message = MessageSender::send($conversation, 'text', ['body' => $body], [
                    'user_id' => Auth::id(),
                    'reply_to_wamid' => $request->str('reply_to') ?: null,
                ]);
            }
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }

        $message['media_url'] = !empty($message['media_path']) ? url('/media/' . $message['media_path']) : null;
        $message['time_label'] = \App\Core\DateHelper::display((string) $message['created_at'], 'h:i A');
        $this->ok(['message' => $message]);
    }

    public function addNote(Request $request): never
    {
        $conversation = $this->findConversation($request);
        $body = $request->str('body');
        if ($body === '') {
            $this->fail(__('inbox.empty_note', 'Note cannot be empty.'), 422);
        }

        // @mentions → targeted notifications
        preg_match_all('/@([\w.\-]+)/u', $body, $mentions);

        $noteId = DB::table('conversation_notes')->insert([
            'tenant_id' => (int) Tenant::id(),
            'conversation_id' => (int) $conversation['id'],
            'user_id' => (int) Auth::id(),
            'body' => $body,
            'mentions' => $mentions[1] ? json_encode($mentions[1]) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($mentions[1] as $mentionName) {
            $mentioned = DB::table('users')->where('tenant_id', Tenant::id())
                ->whereLike('name', str_replace('.', ' ', $mentionName) . '%')->first();
            if ($mentioned !== null) {
                DB::table('notifications')->insert([
                    'tenant_id' => (int) Tenant::id(),
                    'user_id' => (int) $mentioned['id'],
                    'type' => 'mention',
                    'title' => __('inbox.mentioned', ':name mentioned you in a note', ['name' => (string) user()['name']]),
                    'body' => \App\Core\Str::limit($body, 120),
                    'link' => '/tenant/inbox?open=' . $conversation['id'],
                    'created_at' => now(),
                ]);
                Event::publish('notifications', 'notification', [
                    'title' => __('inbox.mentioned', ':name mentioned you in a note', ['name' => (string) user()['name']]),
                ], (int) Tenant::id(), (int) $mentioned['id']);
            }
        }

        Event::publish('inbox', 'note.new', ['conversation_id' => (int) $conversation['id']], (int) Tenant::id());
        $this->ok(['note_id' => $noteId]);
    }

    public function assign(Request $request): never
    {
        $conversation = $this->findConversation($request);
        $assigneeId = $request->int('user_id'); // 0 = unassign

        if ($assigneeId > 0) {
            $assignee = DB::table('users')->where('id', $assigneeId)->where('tenant_id', Tenant::id())->first();
            if ($assignee === null) {
                $this->fail(__('inbox.agent_not_found', 'Agent not found.'), 422);
            }
        }

        DB::table('conversations')->where('id', $conversation['id'])->update([
            'assigned_to' => $assigneeId > 0 ? $assigneeId : null,
            'updated_at' => now(),
        ]);
        DB::table('conversation_events')->insert([
            'tenant_id' => (int) Tenant::id(),
            'conversation_id' => (int) $conversation['id'],
            'user_id' => (int) Auth::id(),
            'type' => $assigneeId > 0 ? 'assigned' : 'unassigned',
            'data' => json_encode(['to' => $assigneeId]),
            'created_at' => now(),
        ]);

        Event::publish('inbox', 'conversation.assigned', [
            'conversation_id' => (int) $conversation['id'],
            'assigned_to' => $assigneeId,
        ], (int) Tenant::id());
        \App\Core\Event::fire('conversation.assigned', ['conversation_id' => $conversation['id'], 'assigned_to' => $assigneeId]);

        $this->ok();
    }

    /**
     * Set status / priority / star / pin / archive in one endpoint.
     */
    public function setStatus(Request $request): never
    {
        $conversation = $this->findConversation($request);
        $update = ['updated_at' => now()];

        $status = $request->str('status');
        if (in_array($status, ['open', 'pending', 'resolved', 'closed'], true)) {
            $update['status'] = $status;
            if (in_array($status, ['resolved', 'closed'], true)) {
                $update['closed_at'] = now();
            }
        }
        $priority = $request->str('priority');
        if (in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $update['priority'] = $priority;
        }
        foreach (['is_starred', 'is_pinned', 'is_archived'] as $flag) {
            $value = $request->input($flag);
            if ($value !== null) {
                $update[$flag] = $request->bool($flag) ? 1 : 0;
            }
        }

        DB::table('conversations')->where('id', $conversation['id'])->update($update);
        Event::publish('inbox', 'conversation.updated', ['conversation_id' => (int) $conversation['id']], (int) Tenant::id());
        $this->ok();
    }

    /**
     * Mark conversation read locally + send read receipts to WhatsApp.
     */
    public function markRead(Request $request): never
    {
        $conversation = $this->findConversation($request);
        DB::table('conversations')->where('id', $conversation['id'])->update(['unread_count' => 0]);

        // Read receipt for the newest inbound message (best-effort)
        $lastInbound = DB::table('messages')
            ->where('conversation_id', $conversation['id'])
            ->where('direction', 'in')
            ->whereNotNull('wamid')
            ->orderBy('id', 'DESC')->first();
        if ($lastInbound !== null && !empty($conversation['phone_number_id'])) {
            try {
                $phone = DB::table('phone_numbers')->where('id', $conversation['phone_number_id'])->first();
                if ($phone !== null) {
                    CloudApiClient::forPhoneNumber($phone)->markRead((string) $phone['phone_number_id'], (string) $lastInbound['wamid']);
                }
            } catch (\Throwable) {
                // read receipts are best-effort
            }
        }
        $this->ok();
    }

    /**
     * Agent typing indicator: broadcast to teammates + WhatsApp typing signal.
     */
    public function typing(Request $request): never
    {
        $conversation = $this->findConversation($request);
        Event::publish('inbox', 'typing', [
            'conversation_id' => (int) $conversation['id'],
            'user_id' => (int) Auth::id(),
            'name' => (string) (user()['name'] ?? ''),
        ], (int) Tenant::id());

        $lastInbound = DB::table('messages')
            ->where('conversation_id', $conversation['id'])
            ->where('direction', 'in')->whereNotNull('wamid')
            ->orderBy('id', 'DESC')->first();
        if ($lastInbound !== null && !empty($conversation['phone_number_id'])) {
            try {
                $phone = DB::table('phone_numbers')->where('id', $conversation['phone_number_id'])->first();
                if ($phone !== null) {
                    CloudApiClient::forPhoneNumber($phone)->markRead((string) $phone['phone_number_id'], (string) $lastInbound['wamid'], true);
                }
            } catch (\Throwable) {
                // best-effort
            }
        }
        $this->ok();
    }

    /**
     * Tenant-scoped conversation lookup — cross-tenant IDs 404.
     */
    private function findConversation(Request $request): array
    {
        $id = (int) $request->route('id');
        $conversation = DB::table('conversations')
            ->where('id', $id)
            ->where('tenant_id', Tenant::id())
            ->first();
        if ($conversation === null) {
            Response::abort(404);
        }
        return $conversation;
    }
}
