<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Tenant;
use App\Models\Contact;
use App\Services\Meta\MessageSender;
use App\Services\Meta\TemplateService;

/**
 * POST /api/v1/messages — send any supported message type.
 * Body (WhatsApp-style): { "to": "9198...", "type": "text|template|image|...",
 *   "text": {...} | "template": {"name","language","variables":{...}} | media fields }
 */
final class MessageApiController extends Controller
{
    public function send(Request $request): never
    {
        $to = $request->str('to');
        $type = $request->str('type', 'text');
        if ($to === '') {
            $this->fail('Field "to" is required.', 422);
        }

        Tenant::setId((int) Tenant::id()); // set by ApiKeyMiddleware
        $contact = Contact::firstOrCreateByPhone($to, null, 'api');

        try {
            if ($type === 'template') {
                $templateData = (array) $request->input('template', []);
                $name = (string) ($templateData['name'] ?? '');
                $language = (string) ($templateData['language'] ?? 'en');
                $template = DB::table('templates')
                    ->where('tenant_id', Tenant::id())
                    ->where('name', $name)->where('language', $language)
                    ->where('status', 'APPROVED')->first();
                if ($template === null) {
                    $this->fail('Approved template not found: ' . $name . ' (' . $language . ')', 422);
                }

                // variables: {"1": "value", ...} → static mapping
                $mapping = [];
                foreach ((array) ($templateData['variables'] ?? []) as $key => $value) {
                    $mapping[(string) $key] = ['source' => 'static', 'value' => is_scalar($value) ? (string) $value : ''];
                }
                if (!empty($templateData['header_media'])) {
                    $mapping['header_media'] = (array) $templateData['header_media'];
                }
                $components = TemplateService::buildSendComponents($template, $mapping, $contact, Contact::fieldValues((int) $contact['id']));
                $message = MessageSender::sendToContact($contact, 'template', [
                    'name' => $template['name'],
                    'language' => $template['language'],
                    'components' => $components,
                ], ['template_category' => strtolower((string) $template['category'])]);
            } else {
                // Free-form: pass the type-specific object straight through
                $content = (array) $request->input($type === 'voice' ? 'audio' : $type, []);
                if ($type === 'text' && isset($content['body'])) {
                    $content = ['body' => (string) $content['body'], 'preview_url' => (bool) ($content['preview_url'] ?? true)];
                }
                $message = MessageSender::sendToContact($contact, $type, $content);
            }
        } catch (\Throwable $e) {
            $this->fail($e->getMessage(), 422);
        }

        $this->json([
            'success' => true,
            'data' => [
                'message_id' => (int) $message['id'],
                'wamid' => $message['wamid'],
                'status' => $message['status'],
                'to' => $contact['phone'],
            ],
        ], 201);
    }

    public function show(Request $request): never
    {
        $message = DB::table('messages')
            ->where('id', (int) $request->route('id'))
            ->where('tenant_id', Tenant::id())
            ->select('id', 'wamid', 'direction', 'type', 'body', 'status', 'error_code', 'error_title',
                'pricing_category', 'cost', 'currency', 'sent_at', 'delivered_at', 'read_at', 'created_at')
            ->first();
        if ($message === null) {
            $this->fail('Message not found.', 404);
        }
        $this->json(['success' => true, 'data' => $message]);
    }
}
