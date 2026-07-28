<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Tenant;

final class ConversationApiController extends Controller
{
    public function index(Request $request): never
    {
        $page = max(1, $request->int('page', 1));
        $result = DB::table('conversations c')
            ->join('contacts', 'c.contact_id', '=', 'contacts.id')
            ->where('c.tenant_id', Tenant::id())
            ->select('c.id', 'c.status', 'c.priority', 'c.unread_count', 'c.assigned_to',
                'c.last_message_preview', 'c.last_message_at', 'c.session_expires_at',
                'contacts.phone', 'contacts.name')
            ->orderBy('c.last_message_at', 'DESC')
            ->paginate($page, min(100, max(1, $request->int('per_page', 25))));

        $this->json(['success' => true, 'data' => $result['data'], 'meta' => [
            'total' => $result['total'], 'page' => $result['page'], 'per_page' => $result['per_page'], 'last_page' => $result['last_page'],
        ]]);
    }

    public function messages(Request $request): never
    {
        $conversation = DB::table('conversations')
            ->where('id', (int) $request->route('id'))
            ->where('tenant_id', Tenant::id())
            ->first();
        if ($conversation === null) {
            $this->fail('Conversation not found.', 404);
        }

        $beforeId = $request->int('before_id');
        $query = DB::table('messages')
            ->where('conversation_id', $conversation['id'])
            ->select('id', 'wamid', 'direction', 'type', 'body', 'status', 'created_at');
        if ($beforeId > 0) {
            $query->where('id', '<', $beforeId);
        }
        $rows = array_reverse($query->orderBy('id', 'DESC')->limit(100)->get());

        $this->json(['success' => true, 'data' => $rows]);
    }
}
