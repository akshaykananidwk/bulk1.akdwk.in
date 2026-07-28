<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Core\DB;
use App\Core\Event;
use App\Core\Logger;
use App\Core\Str;
use App\Core\Tenant;

/**
 * Outbound message pipeline: builds the Graph payload for EVERY supported
 * type (§5.3), enforces the session window, records the message row,
 * calls the API, tracks cost, and publishes the SSE event.
 */
final class MessageSender
{
    /**
     * Send a message within a conversation.
     *
     * $type: text|image|video|audio|voice|document|sticker|location|contacts|
     *        reaction|interactive_button|interactive_list|interactive_cta_url|
     *        interactive_flow|product|product_list|template
     * $content: type-specific fields (see buildPayload()).
     *
     * Returns the stored message row.
     */
    public static function send(array $conversation, string $type, array $content, array $options = []): array
    {
        $tenantId = (int) $conversation['tenant_id'];
        $contact = DB::table('contacts')->where('id', $conversation['contact_id'])->first();
        if ($contact === null) {
            throw new \RuntimeException('Contact not found.');
        }
        if ((int) ($contact['is_blocked'] ?? 0) === 1) {
            throw new \RuntimeException(__('inbox.contact_blocked', 'This contact is blocked.'));
        }

        $phoneNumber = self::resolvePhoneNumber($conversation, $tenantId);
        $normalizedType = self::normalizeType($type);

        // Session-window rule: free-form only inside 24h
        SessionWindowService::assertCanSend($conversation, $normalizedType === 'template' ? 'template' : 'freeform');

        // Plan limit: messages per month
        Tenant::setId($tenantId);
        [$allowed] = Tenant::withinLimit('messages_monthly');
        if (!$allowed) {
            throw new \RuntimeException(__('billing.message_limit', 'Monthly message limit reached. Upgrade your plan to continue sending.'));
        }

        $payload = self::buildPayload($normalizedType, (string) $contact['phone'], $content, $options);

        // Store first (status=queued) so nothing is lost if the API call dies
        $body = self::previewText($normalizedType, $content);
        $messageId = DB::table('messages')->insert([
            'tenant_id' => $tenantId,
            'conversation_id' => (int) $conversation['id'],
            'contact_id' => (int) $contact['id'],
            'phone_number_id' => (int) $phoneNumber['id'],
            'user_id' => $options['user_id'] ?? null,
            'campaign_id' => $options['campaign_id'] ?? null,
            'flow_run_id' => $options['flow_run_id'] ?? null,
            'direction' => 'out',
            'type' => $normalizedType,
            'body' => $body,
            'media_path' => $content['media_path'] ?? null,
            'media_mime' => $content['media_mime'] ?? null,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'queued',
            'context_wamid' => $options['reply_to_wamid'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $client = CloudApiClient::forPhoneNumber($phoneNumber);
            if (!empty($options['reply_to_wamid'])) {
                $payload['context'] = ['message_id' => $options['reply_to_wamid']];
            }
            $response = $client->sendMessage((string) $phoneNumber['phone_number_id'], $payload);
            $wamid = (string) ($response['messages'][0]['id'] ?? '');

            $category = $normalizedType === 'template'
                ? strtolower((string) ($options['template_category'] ?? 'utility'))
                : 'service';
            $cost = PricingService::estimate($tenantId, (string) $contact['phone'], $category);

            DB::table('messages')->where('id', $messageId)->update([
                'wamid' => $wamid !== '' ? $wamid : null,
                'status' => 'sent',
                'pricing_category' => $category,
                'cost' => $cost['rate'],
                'currency' => $cost['currency'],
                'sent_at' => now(),
                'updated_at' => now(),
            ]);

            Tenant::recordUsage('messages_monthly');
        } catch (MetaApiException $e) {
            $friendly = $e->friendly();
            DB::table('messages')->where('id', $messageId)->update([
                'status' => 'failed',
                'error_code' => $e->getCode(),
                'error_title' => mb_substr($friendly['title'], 0, 255),
                'updated_at' => now(),
            ]);
            self::updateConversationAfterSend($conversation, $body, true);
            Event::publish('inbox', 'message.failed', [
                'conversation_id' => (int) $conversation['id'],
                'message_id' => $messageId,
                'error' => $friendly['title'],
                'fix' => $friendly['fix'],
            ], $tenantId);
            throw new \RuntimeException($friendly['title'] . ' ' . $friendly['fix'], $e->getCode(), $e);
        }

        self::updateConversationAfterSend($conversation, $body, false);

        $message = DB::table('messages')->where('id', $messageId)->first() ?? [];
        Event::publish('inbox', 'message.new', [
            'conversation_id' => (int) $conversation['id'],
            'message_id' => $messageId,
            'direction' => 'out',
            'type' => $normalizedType,
            'preview' => Str::limit((string) $body, 80),
        ], $tenantId);

        return $message;
    }

    /**
     * Find-or-create a conversation for a contact then send.
     */
    public static function sendToContact(array $contact, string $type, array $content, array $options = []): array
    {
        $tenantId = (int) $contact['tenant_id'];
        $phoneNumber = DB::table('phone_numbers')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('is_default', 'DESC')
            ->first();
        if ($phoneNumber === null) {
            throw new \RuntimeException(__('whatsapp.no_number', 'No active WhatsApp number connected.'));
        }

        $conversation = DB::table('conversations')
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contact['id'])
            ->where('phone_number_id', $phoneNumber['id'])
            ->first();

        if ($conversation === null) {
            $conversationId = DB::table('conversations')->insert([
                'tenant_id' => $tenantId,
                'contact_id' => (int) $contact['id'],
                'phone_number_id' => (int) $phoneNumber['id'],
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $conversation = DB::table('conversations')->where('id', $conversationId)->first();
        }

        return self::send($conversation, $type, $content, $options);
    }

    // -- Payload construction ---------------------------------------------------

    public static function buildPayload(string $type, string $toPhone, array $content, array $options = []): array
    {
        $base = ['recipient_type' => 'individual', 'to' => $toPhone];

        switch ($type) {
            case 'text':
                return $base + ['type' => 'text', 'text' => [
                    'body' => (string) ($content['body'] ?? ''),
                    'preview_url' => (bool) ($content['preview_url'] ?? true),
                ]];

            case 'image':
            case 'video':
            case 'audio':
            case 'document':
            case 'sticker':
                $media = [];
                if (!empty($content['media_id'])) {
                    $media['id'] = (string) $content['media_id'];
                } elseif (!empty($content['link'])) {
                    $media['link'] = (string) $content['link'];
                } else {
                    throw new \InvalidArgumentException('Media message needs media_id or link.');
                }
                if ($type !== 'audio' && $type !== 'sticker' && !empty($content['caption'])) {
                    $media['caption'] = (string) $content['caption'];
                }
                if ($type === 'document' && !empty($content['filename'])) {
                    $media['filename'] = (string) $content['filename'];
                }
                return $base + ['type' => $type, $type => $media];

            case 'voice':
                // Voice notes are audio with OGG/Opus; API type is audio
                $media = !empty($content['media_id']) ? ['id' => (string) $content['media_id']] : ['link' => (string) ($content['link'] ?? '')];
                return $base + ['type' => 'audio', 'audio' => $media];

            case 'location':
                return $base + ['type' => 'location', 'location' => [
                    'latitude' => (float) ($content['latitude'] ?? 0),
                    'longitude' => (float) ($content['longitude'] ?? 0),
                    'name' => (string) ($content['name'] ?? ''),
                    'address' => (string) ($content['address'] ?? ''),
                ]];

            case 'contacts':
                return $base + ['type' => 'contacts', 'contacts' => (array) ($content['contacts'] ?? [])];

            case 'reaction':
                return $base + ['type' => 'reaction', 'reaction' => [
                    'message_id' => (string) ($content['message_id'] ?? ''),
                    'emoji' => (string) ($content['emoji'] ?? ''),
                ]];

            case 'interactive_button':
                $buttons = [];
                foreach (array_slice((array) ($content['buttons'] ?? []), 0, 3) as $i => $button) {
                    $buttons[] = ['type' => 'reply', 'reply' => [
                        'id' => (string) ($button['id'] ?? ('btn_' . $i)),
                        'title' => mb_substr((string) ($button['title'] ?? ''), 0, 20),
                    ]];
                }
                $interactive = [
                    'type' => 'button',
                    'body' => ['text' => (string) ($content['body'] ?? '')],
                    'action' => ['buttons' => $buttons],
                ];
                if (!empty($content['header'])) {
                    $interactive['header'] = ['type' => 'text', 'text' => (string) $content['header']];
                }
                if (!empty($content['footer'])) {
                    $interactive['footer'] = ['text' => (string) $content['footer']];
                }
                return $base + ['type' => 'interactive', 'interactive' => $interactive];

            case 'interactive_list':
                $sections = [];
                foreach ((array) ($content['sections'] ?? []) as $section) {
                    $rows = [];
                    foreach (array_slice((array) ($section['rows'] ?? []), 0, 10) as $i => $row) {
                        $rows[] = [
                            'id' => (string) ($row['id'] ?? ('row_' . $i)),
                            'title' => mb_substr((string) ($row['title'] ?? ''), 0, 24),
                            'description' => mb_substr((string) ($row['description'] ?? ''), 0, 72),
                        ];
                    }
                    $sections[] = ['title' => mb_substr((string) ($section['title'] ?? ''), 0, 24), 'rows' => $rows];
                }
                return $base + ['type' => 'interactive', 'interactive' => [
                    'type' => 'list',
                    'body' => ['text' => (string) ($content['body'] ?? '')],
                    'action' => [
                        'button' => mb_substr((string) ($content['button'] ?? 'Choose'), 0, 20),
                        'sections' => $sections,
                    ],
                ]];

            case 'interactive_cta_url':
                return $base + ['type' => 'interactive', 'interactive' => [
                    'type' => 'cta_url',
                    'body' => ['text' => (string) ($content['body'] ?? '')],
                    'action' => ['name' => 'cta_url', 'parameters' => [
                        'display_text' => mb_substr((string) ($content['display_text'] ?? 'Open'), 0, 20),
                        'url' => (string) ($content['url'] ?? ''),
                    ]],
                ]];

            case 'interactive_flow':
                return $base + ['type' => 'interactive', 'interactive' => [
                    'type' => 'flow',
                    'body' => ['text' => (string) ($content['body'] ?? '')],
                    'action' => [
                        'name' => 'flow',
                        'parameters' => [
                            'flow_message_version' => '3',
                            'flow_id' => (string) ($content['flow_id'] ?? ''),
                            'flow_cta' => mb_substr((string) ($content['cta'] ?? 'Open'), 0, 20),
                            'flow_action' => (string) ($content['flow_action'] ?? 'navigate'),
                        ],
                    ],
                ]];

            case 'product':
                return $base + ['type' => 'interactive', 'interactive' => [
                    'type' => 'product',
                    'body' => ['text' => (string) ($content['body'] ?? '')],
                    'action' => [
                        'catalog_id' => (string) ($content['catalog_id'] ?? ''),
                        'product_retailer_id' => (string) ($content['product_retailer_id'] ?? ''),
                    ],
                ]];

            case 'product_list':
                return $base + ['type' => 'interactive', 'interactive' => [
                    'type' => 'product_list',
                    'header' => ['type' => 'text', 'text' => (string) ($content['header'] ?? 'Products')],
                    'body' => ['text' => (string) ($content['body'] ?? '')],
                    'action' => [
                        'catalog_id' => (string) ($content['catalog_id'] ?? ''),
                        'sections' => (array) ($content['sections'] ?? []),
                    ],
                ]];

            case 'template':
                $components = [];
                foreach ((array) ($content['components'] ?? []) as $component) {
                    $components[] = $component;
                }
                return $base + ['type' => 'template', 'template' => [
                    'name' => (string) ($content['name'] ?? ''),
                    'language' => ['code' => (string) ($content['language'] ?? 'en')],
                    'components' => $components,
                ]];

            default:
                throw new \InvalidArgumentException('Unsupported message type: ' . $type);
        }
    }

    // -- Helpers ------------------------------------------------------------------

    private static function normalizeType(string $type): string
    {
        $map = [
            'interactive.button' => 'interactive_button',
            'interactive.list' => 'interactive_list',
            'interactive.cta_url' => 'interactive_cta_url',
            'interactive.flow' => 'interactive_flow',
            'interactive.product' => 'product',
            'interactive.product_list' => 'product_list',
        ];
        return $map[$type] ?? $type;
    }

    private static function resolvePhoneNumber(array $conversation, int $tenantId): array
    {
        if (!empty($conversation['phone_number_id'])) {
            $phone = DB::table('phone_numbers')->where('id', $conversation['phone_number_id'])->first();
            if ($phone !== null) {
                return $phone;
            }
        }
        $phone = DB::table('phone_numbers')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderBy('is_default', 'DESC')
            ->first();
        if ($phone === null) {
            throw new \RuntimeException(__('whatsapp.no_number', 'No active WhatsApp number connected.'));
        }
        return $phone;
    }

    private static function previewText(string $type, array $content): string
    {
        return match ($type) {
            'text' => (string) ($content['body'] ?? ''),
            'template' => '[' . __('inbox.type_template', 'Template') . ': ' . ($content['name'] ?? '') . ']',
            'image' => '📷 ' . ($content['caption'] ?? __('inbox.type_image', 'Photo')),
            'video' => '🎥 ' . ($content['caption'] ?? __('inbox.type_video', 'Video')),
            'audio', 'voice' => '🎤 ' . __('inbox.type_audio', 'Audio'),
            'document' => '📄 ' . ($content['filename'] ?? __('inbox.type_document', 'Document')),
            'sticker' => '💟 ' . __('inbox.type_sticker', 'Sticker'),
            'location' => '📍 ' . ($content['name'] ?? __('inbox.type_location', 'Location')),
            'contacts' => '👤 ' . __('inbox.type_contact', 'Contact card'),
            'reaction' => (string) ($content['emoji'] ?? '👍'),
            'interactive_button', 'interactive_list', 'interactive_cta_url', 'interactive_flow' => (string) ($content['body'] ?? ''),
            'product', 'product_list' => '🛍️ ' . ($content['body'] ?? __('inbox.type_product', 'Product')),
            default => '',
        };
    }

    private static function updateConversationAfterSend(array $conversation, string $preview, bool $failed): void
    {
        DB::table('conversations')->where('id', $conversation['id'])->update([
            'last_message_preview' => mb_substr($preview, 0, 255),
            'last_message_at' => now(),
            'last_outbound_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
