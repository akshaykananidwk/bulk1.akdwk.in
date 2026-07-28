<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\DB;
use App\Services\Meta\MessageSender;

/**
 * Async message send (used by flows, API and campaign retries).
 * Payload: conversation_id OR contact_id, type, content, options.
 */
final class SendMessageJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $type = (string) ($payload['type'] ?? 'text');
        $content = (array) ($payload['content'] ?? []);
        $options = (array) ($payload['options'] ?? []);

        if (!empty($payload['conversation_id'])) {
            $conversation = DB::table('conversations')->where('id', (int) $payload['conversation_id'])->first();
            if ($conversation === null) {
                return;
            }
            MessageSender::send($conversation, $type, $content, $options);
            return;
        }

        if (!empty($payload['contact_id'])) {
            $contact = DB::table('contacts')->where('id', (int) $payload['contact_id'])->first();
            if ($contact === null) {
                return;
            }
            MessageSender::sendToContact($contact, $type, $content, $options);
        }
    }
}
