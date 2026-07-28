<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\DB;
use App\Core\Event;
use App\Core\Storage;
use App\Services\Meta\CloudApiClient;

/**
 * Downloads inbound media from Meta and stores it under uploads/.
 */
final class DownloadMediaJob implements JobInterface
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/3gpp' => '3gp',
        'audio/aac' => 'aac',
        'audio/mp4' => 'mp3',
        'audio/mpeg' => 'mp3',
        'audio/amr' => 'amr',
        'audio/ogg' => 'ogg',
        'audio/opus' => 'ogg',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
    ];

    public function handle(array $payload): void
    {
        $messageId = (int) ($payload['message_id'] ?? 0);
        $mediaMetaId = (string) ($payload['media_meta_id'] ?? '');
        $phoneRowId = (int) ($payload['phone_number_row_id'] ?? 0);
        if ($messageId <= 0 || $mediaMetaId === '') {
            return;
        }

        $message = DB::table('messages')->where('id', $messageId)->first();
        $phone = DB::table('phone_numbers')->where('id', $phoneRowId)->first();
        if ($message === null || $phone === null || !empty($message['media_path'])) {
            return;
        }

        $client = CloudApiClient::forPhoneNumber($phone);
        $info = $client->getMediaUrl($mediaMetaId);
        $url = (string) ($info['url'] ?? '');
        $mime = (string) ($info['mime_type'] ?? ($message['media_mime'] ?? 'application/octet-stream'));
        if ($url === '') {
            throw new \RuntimeException('Media URL missing for ' . $mediaMetaId);
        }

        $bytes = $client->downloadMedia($url);
        // Enforce Meta's documented cap (100MB documents) as a safety net
        if (strlen($bytes) > 100 * 1024 * 1024) {
            throw new \RuntimeException('Media exceeds size cap');
        }

        $extension = self::EXTENSIONS[strtolower($mime)] ?? 'bin';
        $relative = 'media/' . $message['tenant_id'] . '/' . date('Y/m') . '/' . \App\Core\Hash::token(16) . '.' . $extension;
        Storage::putContents($relative, $bytes);

        DB::table('messages')->where('id', $messageId)->update([
            'media_path' => $relative,
            'media_mime' => $mime,
            'updated_at' => now(),
        ]);

        Event::publish('inbox', 'message.media_ready', [
            'conversation_id' => (int) $message['conversation_id'],
            'message_id' => $messageId,
            'media_url' => url('/media/' . $relative),
        ], (int) $message['tenant_id']);
    }
}
