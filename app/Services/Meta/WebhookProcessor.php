<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Core\DB;
use App\Core\Event;
use App\Core\Logger;
use App\Core\Str;
use App\Models\Contact;

/**
 * Processes queued Meta webhook payloads: inbound messages of every type,
 * status updates (with pricing), template status changes, quality updates.
 * Idempotent on wamid + status.
 */
final class WebhookProcessor
{
    public static function process(array $payload): void
    {
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $field = (string) ($change['field'] ?? '');
                $value = (array) ($change['value'] ?? []);

                match ($field) {
                    'messages' => self::handleMessages($value),
                    'message_template_status_update' => self::handleTemplateStatus($value),
                    'template_category_update' => self::handleTemplateCategory($value),
                    'phone_number_quality_update' => self::handleQualityUpdate($value),
                    'account_update', 'account_alerts', 'business_capability_update' => self::handleAccountUpdate($field, $value),
                    // Coexistence (WhatsApp Business app + Cloud API on one number)
                    'smb_message_echoes' => self::handleMessageEchoes($value),
                    'history' => self::handleHistory($value),
                    'smb_app_state_sync' => self::handleAppStateSync($value),
                    default => Logger::channel('webhook')->info('Unhandled webhook field: ' . $field),
                };
            }
        }
    }

    // -- messages field: inbound messages AND status updates ---------------------

    private static function handleMessages(array $value): void
    {
        $metadata = (array) ($value['metadata'] ?? []);
        $phoneNumberId = (string) ($metadata['phone_number_id'] ?? '');

        $phoneNumber = $phoneNumberId !== ''
            ? DB::table('phone_numbers')->where('phone_number_id', $phoneNumberId)->first()
            : null;

        if ($phoneNumber === null) {
            Logger::channel('webhook')->warning('Webhook for unknown phone_number_id', ['id' => $phoneNumberId]);
            return;
        }
        $tenantId = (int) $phoneNumber['tenant_id'];

        // Inbound messages
        $contactsInfo = [];
        foreach ((array) ($value['contacts'] ?? []) as $info) {
            $contactsInfo[(string) ($info['wa_id'] ?? '')] = (string) ($info['profile']['name'] ?? '');
        }

        foreach ((array) ($value['messages'] ?? []) as $message) {
            self::handleInboundMessage($tenantId, $phoneNumber, $message, $contactsInfo);
        }

        // Status updates
        foreach ((array) ($value['statuses'] ?? []) as $status) {
            self::handleStatus($tenantId, $status);
        }
    }

    // -- Coexistence fields -------------------------------------------------------

    private static function phoneFromValue(array $value): ?array
    {
        $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
        $phone = $phoneNumberId !== ''
            ? DB::table('phone_numbers')->where('phone_number_id', $phoneNumberId)->first()
            : null;
        if ($phone === null) {
            Logger::channel('webhook')->warning('Coexistence webhook for unknown phone_number_id', ['id' => $phoneNumberId]);
        }
        return $phone;
    }

    /** Messages the business sent from the WhatsApp Business mobile app. */
    private static function handleMessageEchoes(array $value): void
    {
        $phone = self::phoneFromValue($value);
        if ($phone === null) {
            return;
        }
        foreach ((array) ($value['message_echoes'] ?? []) as $message) {
            self::storeSyncedMessage($phone, (string) ($message['to'] ?? ''), $message, 'out', true);
        }
    }

    /** Up to 180 days of chat history shared from the WhatsApp Business app. */
    private static function handleHistory(array $value): void
    {
        $phone = self::phoneFromValue($value);
        if ($phone === null) {
            return;
        }
        foreach ((array) ($value['history'] ?? []) as $chunk) {
            if (!empty($chunk['errors'])) {
                // e.g. the business declined to share history
                Logger::channel('webhook')->info('History sync not shared', ['errors' => $chunk['errors']]);
                continue;
            }
            foreach ((array) ($chunk['threads'] ?? []) as $thread) {
                $contactPhone = (string) ($thread['id'] ?? '');
                foreach ((array) ($thread['messages'] ?? []) as $message) {
                    $direction = ((string) ($message['from'] ?? '')) === $contactPhone ? 'in' : 'out';
                    self::storeSyncedMessage($phone, $contactPhone, $message, $direction, false);
                }
            }
        }
    }

    /** Contacts from the WhatsApp Business app's address book. */
    private static function handleAppStateSync(array $value): void
    {
        $phone = self::phoneFromValue($value);
        if ($phone === null) {
            return;
        }
        \App\Core\Tenant::setId((int) $phone['tenant_id']);
        foreach ((array) ($value['state_sync'] ?? []) as $item) {
            if (($item['type'] ?? '') !== 'contact' || ($item['action'] ?? 'add') !== 'add') {
                continue;
            }
            $number = (string) ($item['contact']['phone_number'] ?? '');
            if ($number === '') {
                continue;
            }
            $name = (string) ($item['contact']['full_name'] ?? ($item['contact']['first_name'] ?? ''));
            Contact::firstOrCreateByPhone($number, $name !== '' ? $name : null, 'whatsapp_app');
        }
    }

    /**
     * Store an echo / history message without firing bots, unread counters or
     * opt-out handling (the business already handled these on the phone).
     */
    private static function storeSyncedMessage(array $phone, string $contactPhone, array $message, string $direction, bool $live): void
    {
        $wamid = (string) ($message['id'] ?? '');
        if ($wamid === '' || $contactPhone === '' || DB::table('messages')->where('wamid', $wamid)->exists()) {
            return;
        }
        $tenantId = (int) $phone['tenant_id'];
        \App\Core\Tenant::setId($tenantId);
        $contact = Contact::firstOrCreateByPhone($contactPhone, null, 'whatsapp_app');

        $conversation = DB::table('conversations')
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contact['id'])
            ->where('phone_number_id', $phone['id'])
            ->first();
        $conversationId = $conversation !== null ? (int) $conversation['id'] : DB::table('conversations')->insert([
            'tenant_id' => $tenantId,
            'contact_id' => (int) $contact['id'],
            'phone_number_id' => (int) $phone['id'],
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [$type, $body, $mediaMetaId, $mediaMime] = self::parseInbound($message);
        $timestamp = isset($message['timestamp']) ? date('Y-m-d H:i:s', (int) $message['timestamp']) : now();
        $status = strtolower((string) ($message['history_context']['status'] ?? ($direction === 'in' ? 'delivered' : 'sent')));
        if (!in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) {
            $status = $direction === 'in' ? 'delivered' : 'sent';
        }

        $messageId = DB::table('messages')->insert([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'contact_id' => (int) $contact['id'],
            'phone_number_id' => (int) $phone['id'],
            'wamid' => $wamid,
            'direction' => $direction,
            'type' => $type,
            'body' => $body,
            'media_meta_id' => $mediaMetaId,
            'media_mime' => $mediaMime,
            'payload' => json_encode($message, JSON_UNESCAPED_UNICODE),
            'status' => $status,
            'created_at' => $timestamp,
            'updated_at' => now(),
        ]);

        // Meta keeps media downloadable for a limited time, so only fetch recent files
        if ($mediaMetaId !== null && strtotime($timestamp) >= time() - 14 * 86400) {
            \App\Core\Queue::push(\App\Jobs\DownloadMediaJob::class, [
                'message_id' => $messageId,
                'media_meta_id' => $mediaMetaId,
                'phone_number_row_id' => (int) $phone['id'],
            ], 'media', 7, 0, $tenantId);
        }

        // Keep the conversation preview current (only if this message is newer)
        $prefix = DB::prefix();
        DB::query(
            'UPDATE `' . $prefix . 'conversations` SET `last_message_preview` = ?, `last_message_at` = ?, `updated_at` = ? '
            . 'WHERE `id` = ? AND (`last_message_at` IS NULL OR `last_message_at` <= ?)',
            [mb_substr($body, 0, 255), $timestamp, now(), $conversationId, $timestamp]
        );

        if ($live) {
            // The business replied from the phone app, so the chat has been read there
            DB::table('conversations')->where('id', $conversationId)->update([
                'unread_count' => 0,
                'last_outbound_at' => $timestamp,
            ]);
            Event::publish('inbox', 'message.new', [
                'conversation_id' => $conversationId,
                'direction' => $direction,
                'type' => $type,
                'contact_name' => $contact['name'] ?? $contactPhone,
                'preview' => Str::limit($body, 80),
            ], $tenantId);
        }
    }

    private static function handleInboundMessage(int $tenantId, array $phoneNumber, array $message, array $contactsInfo): void
    {
        $wamid = (string) ($message['id'] ?? '');
        if ($wamid === '') {
            return;
        }

        // Idempotency: skip if we already stored this wamid
        if (DB::table('messages')->where('wamid', $wamid)->exists()) {
            return;
        }

        \App\Core\Tenant::setId($tenantId);

        $fromPhone = (string) ($message['from'] ?? '');
        $profileName = $contactsInfo[$fromPhone] ?? null;
        $contact = Contact::firstOrCreateByPhone($fromPhone, $profileName, 'inbound');

        // Conversation (per contact + our phone number)
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
        } else {
            $conversationId = (int) $conversation['id'];
        }

        [$type, $body, $mediaMetaId, $mediaMime, $extra] = self::parseInbound($message);

        $timestamp = isset($message['timestamp']) ? date('Y-m-d H:i:s', (int) $message['timestamp']) : now();

        $messageId = DB::table('messages')->insert([
            'tenant_id' => $tenantId,
            'conversation_id' => $conversationId,
            'contact_id' => (int) $contact['id'],
            'phone_number_id' => (int) $phoneNumber['id'],
            'wamid' => $wamid,
            'direction' => 'in',
            'type' => $type,
            'body' => $body,
            'media_meta_id' => $mediaMetaId,
            'media_mime' => $mediaMime,
            'payload' => json_encode($message, JSON_UNESCAPED_UNICODE),
            'status' => 'delivered',
            'context_wamid' => $message['context']['id'] ?? null,
            'referral' => isset($message['referral']) ? json_encode($message['referral'], JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $timestamp,
            'updated_at' => now(),
        ]);

        // 24h session window extends on every inbound
        SessionWindowService::touchInbound($conversationId);

        // Conversation list bookkeeping (reopen if resolved)
        $prefix = DB::prefix();
        DB::query(
            'UPDATE `' . $prefix . 'conversations` SET '
            . '`last_message_preview` = ?, `last_message_at` = ?, `unread_count` = `unread_count` + 1, '
            . "`status` = IF(`status` IN ('resolved','closed'), 'open', `status`), `is_archived` = 0, `updated_at` = ? WHERE `id` = ?",
            [mb_substr($body, 0, 255), $timestamp, now(), $conversationId]
        );

        DB::table('contacts')->where('id', $contact['id'])->update(['last_message_at' => $timestamp]);

        // Media download job (async, keeps webhook processing fast)
        if ($mediaMetaId !== null) {
            \App\Core\Queue::push(\App\Jobs\DownloadMediaJob::class, [
                'message_id' => $messageId,
                'media_meta_id' => $mediaMetaId,
                'phone_number_row_id' => (int) $phoneNumber['id'],
            ], 'media', 5, 0, $tenantId);
        }

        // Compliance: STOP/START keywords
        $wasOptCommand = $type === 'text' && Contact::handleOptKeywords($contact, $body);

        // Real-time UI event
        Event::publish('inbox', 'message.new', [
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'direction' => 'in',
            'type' => $type,
            'contact_name' => $contact['name'] ?? $fromPhone,
            'preview' => Str::limit($body, 80),
        ], $tenantId);

        \App\Core\Event::fire('message.received', [
            'tenant_id' => $tenantId,
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'contact' => $contact,
            'type' => $type,
            'body' => $body,
            'extra' => $extra,
        ]);

        // Bot triggers (keywords / flows) — skipped for opt commands
        if (!$wasOptCommand && class_exists(\App\Services\Automation\TriggerMatcher::class)) {
            try {
                \App\Services\Automation\TriggerMatcher::onInboundMessage($tenantId, $conversationId, $contact, $type, $body, $extra);
            } catch (\Throwable $e) {
                Logger::channel('app')->error('Trigger matching failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * @return array{0:string,1:string,2:?string,3:?string,4:array} [type, body, mediaId, mime, extra]
     */
    /**
     * Turn a Meta message object into [type, body, media id, mime, extra].
     * Public so the inbox can re-describe rows stored as "[type]" by older
     * versions (the raw message is kept in messages.payload).
     */
    public static function parseInbound(array $message): array
    {
        $type = (string) ($message['type'] ?? 'unknown');

        switch ($type) {
            case 'text':
                return ['text', (string) ($message['text']['body'] ?? ''), null, null, []];
            case 'image':
            case 'video':
            case 'audio':
            case 'voice':
            case 'document':
            case 'sticker':
                $media = (array) ($message[$type] ?? []);
                $caption = (string) ($media['caption'] ?? '');
                $isVoice = $type === 'voice' || !empty($media['voice']);
                $body = $caption !== '' ? $caption : match ($type) {
                    'image' => '📷 Photo',
                    'video' => '🎥 Video',
                    'audio', 'voice' => $isVoice ? '🎤 Voice message' : '🎵 Audio',
                    'document' => '📄 ' . ($media['filename'] ?? 'Document'),
                    'sticker' => '💟 Sticker',
                };
                return [$type === 'voice' ? 'audio' : $type, $body, $media['id'] ?? null, $media['mime_type'] ?? null, ['filename' => $media['filename'] ?? null]];
            case 'location':
                $location = (array) ($message['location'] ?? []);
                $lat = $location['latitude'] ?? null;
                $lng = $location['longitude'] ?? null;
                $parts = array_filter([(string) ($location['name'] ?? ''), (string) ($location['address'] ?? '')], 'strlen');
                $body = '📍 ' . ($parts !== [] ? implode(', ', $parts) : 'Location');
                if ($lat !== null && $lng !== null) {
                    $body .= "\nhttps://maps.google.com/?q=" . $lat . ',' . $lng;
                }
                return ['location', $body, null, null, $location];
            case 'contacts':
                $lines = [];
                foreach ((array) ($message['contacts'] ?? []) as $card) {
                    $name = (string) ($card['name']['formatted_name'] ?? ($card['name']['first_name'] ?? ''));
                    $phones = array_filter(array_map(fn ($p) => (string) ($p['phone'] ?? ($p['wa_id'] ?? '')), (array) ($card['phones'] ?? [])), 'strlen');
                    $lines[] = trim(($name !== '' ? $name : 'Contact') . ($phones !== [] ? ' — ' . implode(', ', $phones) : ''));
                }
                return ['contacts', '👤 ' . ($lines !== [] ? implode("\n👤 ", $lines) : 'Contact card'), null, null, ['contacts' => $message['contacts'] ?? []]];
            case 'button':
                $text = (string) ($message['button']['text'] ?? '');
                return ['button_reply', '🔘 ' . $text, null, null, ['payload' => $message['button']['payload'] ?? null]];
            case 'interactive':
                $interactive = (array) ($message['interactive'] ?? []);
                $subtype = (string) ($interactive['type'] ?? '');
                if ($subtype === 'button_reply') {
                    $reply = (array) ($interactive['button_reply'] ?? []);
                    return ['button_reply', '🔘 ' . (string) ($reply['title'] ?? ''), null, null, ['id' => $reply['id'] ?? null]];
                }
                if ($subtype === 'list_reply') {
                    $reply = (array) ($interactive['list_reply'] ?? []);
                    $body = '📋 ' . (string) ($reply['title'] ?? '') . (!empty($reply['description']) ? ' — ' . $reply['description'] : '');
                    return ['list_reply', $body, null, null, ['id' => $reply['id'] ?? null]];
                }
                if ($subtype === 'nfm_reply') {
                    $reply = (array) ($interactive['nfm_reply'] ?? []);
                    return ['flow_reply', '📝 ' . (string) ($reply['body'] ?? 'Flow response'), null, null, ['response' => $reply['response_json'] ?? null]];
                }
                // Business-sent interactive (seen in coexistence echoes/history)
                $text = (string) ($interactive['body']['text'] ?? '');
                return ['interactive', $text !== '' ? $text : '🔘 Interactive message' . ($subtype !== '' ? ' (' . $subtype . ')' : ''), null, null, ['subtype' => $subtype]];
            case 'template':
                // Business-sent template (coexistence echoes/history)
                $name = (string) ($message['template']['name'] ?? '');
                return ['template', '📨 Template' . ($name !== '' ? ': ' . $name : ''), null, null, []];
            case 'order':
                $order = (array) ($message['order'] ?? []);
                return ['order', '🛒 Order (' . count((array) ($order['product_items'] ?? [])) . ' items)', null, null, $order];
            case 'reaction':
                $reaction = (array) ($message['reaction'] ?? []);
                $emoji = (string) ($reaction['emoji'] ?? '');
                return ['reaction', $emoji !== '' ? 'Reacted ' . $emoji : 'Removed a reaction', null, null, ['message_id' => $reaction['message_id'] ?? null]];
            case 'system':
                return ['system', 'ℹ️ ' . (string) ($message['system']['body'] ?? 'System update'), null, null, []];
            case 'request_welcome':
                return ['system', '👋 Opened the chat', null, null, []];
            case 'edit':
                $edited = (array) ($message['edit']['message'] ?? ($message['edit'] ?? []));
                $text = (string) ($edited['text']['body'] ?? ($edited['body'] ?? ''));
                return ['edit', '✏️ Edited: ' . ($text !== '' ? $text : 'message edited'), null, null, []];
            case 'revoke':
                return ['revoke', '🚫 Message deleted', null, null, []];
            case 'unsupported':
                // Polls, view-once, events etc. Meta says what it was in
                // unsupported.type or in the error details.
                $what = (string) ($message['unsupported']['type'] ?? '');
                if ($what === '') {
                    $error = (array) (($message['errors'] ?? [])[0] ?? []);
                    $what = (string) ($error['error_data']['details'] ?? ($error['title'] ?? ($error['message'] ?? '')));
                }
                return ['unsupported', 'Unsupported message type' . ($what !== '' ? ': ' . $what : ''), null, null, []];
            default:
                return [$type, 'Unsupported message type: ' . $type, null, null, []];
        }
    }

    // -- statuses --------------------------------------------------------------------

    private static function handleStatus(int $tenantId, array $status): void
    {
        $wamid = (string) ($status['id'] ?? '');
        $state = (string) ($status['status'] ?? '');
        if ($wamid === '' || $state === '') {
            return;
        }

        $message = DB::table('messages')->where('wamid', $wamid)->first();
        if ($message === null) {
            return;
        }

        // Idempotency: unique (wamid, status) in message_status_logs
        try {
            DB::table('message_status_logs')->insert([
                'tenant_id' => (int) $message['tenant_id'],
                'message_id' => (int) $message['id'],
                'wamid' => $wamid,
                'status' => $state,
                'error_code' => isset($status['errors'][0]['code']) ? (int) $status['errors'][0]['code'] : null,
                'raw' => json_encode($status, JSON_UNESCAPED_UNICODE),
                'occurred_at' => isset($status['timestamp']) ? date('Y-m-d H:i:s', (int) $status['timestamp']) : now(),
            ]);
        } catch (\Throwable) {
            return; // duplicate webhook — already handled, never double-count
        }

        // Progression guard: never downgrade read → delivered
        $rank = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4, 'deleted' => 5];
        $currentRank = $rank[$message['status']] ?? 0;
        $newRank = $rank[$state] ?? 0;

        $update = ['updated_at' => now()];
        if ($state === 'sent') {
            $update['sent_at'] = now();
        }
        if ($state === 'delivered') {
            $update['delivered_at'] = now();
        }
        if ($state === 'read') {
            $update['read_at'] = now();
        }

        if ($state === 'failed') {
            $error = (array) ($status['errors'][0] ?? []);
            $code = (int) ($error['code'] ?? 0);
            $friendly = ErrorCodeMapper::map($code, (string) ($error['title'] ?? ''));
            $update['status'] = 'failed';
            $update['error_code'] = $code;
            $update['error_title'] = mb_substr($friendly['title'], 0, 255);
        } elseif ($newRank > $currentRank) {
            $update['status'] = $state;
        }

        DB::table('messages')->where('id', $message['id'])->update($update);

        // Pricing info arrives with sent/delivered statuses — authoritative
        if (isset($status['pricing']) || isset($status['conversation'])) {
            PricingService::applyWebhookPricing($wamid, (array) ($status['pricing'] ?? []), $status['conversation'] ?? null);
        }

        // Campaign recipient stats
        if (!empty($message['campaign_id'])) {
            self::bumpCampaignStatus((int) $message['campaign_id'], (int) $message['id'], $state);
        }

        Event::publish('inbox', 'message.status', [
            'conversation_id' => (int) $message['conversation_id'],
            'message_id' => (int) $message['id'],
            'status' => $state,
        ], (int) $message['tenant_id']);
    }

    private static function bumpCampaignStatus(int $campaignId, int $messageId, string $state): void
    {
        $recipient = DB::table('campaign_recipients')->where('message_id', $messageId)->first();
        if ($recipient === null) {
            return;
        }
        $map = ['sent' => 'sent', 'delivered' => 'delivered', 'read' => 'read', 'failed' => 'failed'];
        if (!isset($map[$state])) {
            return;
        }
        $rank = ['pending' => 0, 'queued' => 1, 'sent' => 2, 'delivered' => 3, 'read' => 4, 'replied' => 5, 'failed' => 6, 'skipped' => 7];
        if (($rank[$map[$state]] ?? 0) > ($rank[$recipient['status']] ?? 0)) {
            DB::table('campaign_recipients')->where('id', $recipient['id'])->update(['status' => $map[$state]]);
        }
        $column = $map[$state] . '_count';
        DB::table('campaigns')->where('id', $campaignId)->increment($column);
    }

    // -- template + account updates ----------------------------------------------------

    private static function handleTemplateStatus(array $value): void
    {
        $metaTemplateId = (string) ($value['message_template_id'] ?? '');
        $name = (string) ($value['message_template_name'] ?? '');
        $language = (string) ($value['message_template_language'] ?? '');
        $event = strtoupper((string) ($value['event'] ?? ''));
        $reason = $value['reason'] ?? null;

        $statusMap = ['APPROVED' => 'APPROVED', 'REJECTED' => 'REJECTED', 'PAUSED' => 'PAUSED', 'PENDING' => 'PENDING', 'DISABLED' => 'DISABLED'];
        if (!isset($statusMap[$event])) {
            return;
        }

        $query = DB::table('templates');
        if ($metaTemplateId !== '') {
            $query->where('meta_template_id', $metaTemplateId);
        } else {
            $query->where('name', $name)->where('language', $language);
        }
        $template = $query->first();
        if ($template === null) {
            return;
        }

        DB::table('templates')->where('id', $template['id'])->update([
            'status' => $statusMap[$event],
            'rejected_reason' => is_string($reason) ? $reason : null,
            'updated_at' => now(),
        ]);

        self::notifyTenant((int) $template['tenant_id'], 'template.status', __('templates.status_changed', 'Template status changed'),
            $template['name'] . ' → ' . $statusMap[$event] . ($reason ? ' (' . $reason . ')' : ''), '/tenant/templates');
    }

    private static function handleTemplateCategory(array $value): void
    {
        $name = (string) ($value['message_template_name'] ?? '');
        $newCategory = strtoupper((string) ($value['new_category'] ?? ''));
        if ($name === '' || !in_array($newCategory, ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true)) {
            return;
        }
        DB::table('templates')->where('name', $name)->update([
            'category' => $newCategory,
            'updated_at' => now(),
        ]);
    }

    private static function handleQualityUpdate(array $value): void
    {
        $phoneNumberId = (string) ($value['display_phone_number'] ?? '');
        $event = (string) ($value['event'] ?? '');
        $limit = (string) ($value['current_limit'] ?? '');

        $phone = DB::table('phone_numbers')->where('display_phone_number', $phoneNumberId)->first();
        if ($phone === null && isset($value['phone_number_id'])) {
            $phone = DB::table('phone_numbers')->where('phone_number_id', (string) $value['phone_number_id'])->first();
        }
        if ($phone === null) {
            return;
        }

        $update = ['updated_at' => now()];
        if ($limit !== '') {
            $update['messaging_limit_tier'] = $limit;
        }
        if (stripos($event, 'flag') !== false) {
            $update['status'] = 'flagged';
            $update['quality_rating'] = 'RED';
        } elseif (stripos($event, 'unflag') !== false) {
            $update['status'] = 'active';
            $update['quality_rating'] = 'GREEN';
        }
        DB::table('phone_numbers')->where('id', $phone['id'])->update($update);

        self::notifyTenant((int) $phone['tenant_id'], 'quality.update',
            __('whatsapp.quality_changed', 'WhatsApp number quality update'),
            $phone['display_phone_number'] . ': ' . $event . ($limit ? ' (limit: ' . $limit . ')' : ''), '/tenant/whatsapp');
    }

    private static function handleAccountUpdate(string $field, array $value): void
    {
        Logger::channel('webhook')->info('Account update: ' . $field, $value);
        $wabaId = (string) ($value['waba_info']['waba_id'] ?? ($value['waba_id'] ?? ''));
        if ($wabaId === '') {
            return;
        }
        $waba = DB::table('waba_accounts')->where('waba_id', $wabaId)->first();
        if ($waba === null) {
            return;
        }
        $event = (string) ($value['event'] ?? $field);
        if (in_array($event, ['DISABLED_UPDATE', 'ACCOUNT_DELETED', 'ACCOUNT_RESTRICTION'], true)) {
            DB::table('waba_accounts')->where('id', $waba['id'])->update([
                'status' => 'error',
                'status_reason' => mb_substr(__('whatsapp.reason_meta', 'Meta reported: ') . $event . (!empty($value['ban_info']['waba_ban_state']) ? ' — ' . $value['ban_info']['waba_ban_state'] : ''), 0, 255),
                'updated_at' => now(),
            ]);
        }
        self::notifyTenant((int) $waba['tenant_id'], 'account.update',
            __('whatsapp.account_update', 'WhatsApp account update'), $event, '/tenant/whatsapp');
    }

    private static function notifyTenant(int $tenantId, string $type, string $title, string $body, string $link): void
    {
        DB::table('notifications')->insert([
            'tenant_id' => $tenantId,
            'user_id' => null,
            'type' => $type,
            'title' => mb_substr($title, 0, 255),
            'body' => $body,
            'link' => $link,
            'created_at' => now(),
        ]);
        Event::publish('notifications', 'notification', ['title' => $title, 'body' => $body, 'link' => $link], $tenantId);
    }
}
