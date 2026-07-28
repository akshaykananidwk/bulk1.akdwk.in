<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Core\DB;
use App\Core\Storage;

/**
 * Outbound media handling: validate against Meta limits, upload to Meta,
 * cache media_id for 30 days for reuse in campaigns.
 */
final class MediaService
{
    private const TYPE_BY_MIME = [
        'image/jpeg' => 'image', 'image/png' => 'image', 'image/webp' => 'sticker',
        'video/mp4' => 'video', 'video/3gpp' => 'video',
        'audio/aac' => 'audio', 'audio/mp4' => 'audio', 'audio/mpeg' => 'audio',
        'audio/amr' => 'audio', 'audio/ogg' => 'audio',
        'application/pdf' => 'document', 'text/plain' => 'document', 'text/csv' => 'document',
        'application/msword' => 'document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
        'application/vnd.ms-excel' => 'document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'document',
        'application/vnd.ms-powerpoint' => 'document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'document',
    ];

    /**
     * Validate a local file against Meta's per-type size limits.
     * Returns the WhatsApp media type. Throws with a friendly message.
     */
    public static function validate(string $absolutePath, string $mime): string
    {
        $type = self::TYPE_BY_MIME[strtolower($mime)] ?? null;
        if ($type === null) {
            throw new \RuntimeException(__('media.unsupported', 'This file type cannot be sent on WhatsApp.'));
        }

        $size = (int) @filesize($absolutePath);
        $limits = (array) config('meta.media_limits', []);
        $limit = match ($type) {
            'image' => (int) ($limits['image'] ?? 5242880),
            'video' => (int) ($limits['video'] ?? 16777216),
            'audio' => (int) ($limits['audio'] ?? 16777216),
            'document' => (int) ($limits['document'] ?? 104857600),
            'sticker' => (int) ($limits['sticker_static'] ?? 102400),
            default => 5242880,
        };

        if ($size > $limit) {
            throw new \RuntimeException(__('media.too_large', 'File too large for WhatsApp: ')
                . \App\Core\Str::humanBytes($size) . ' > ' . \App\Core\Str::humanBytes($limit));
        }

        return $type;
    }

    /**
     * Upload media to Meta (or return the cached media_id if fresh),
     * recording it in media_library.
     * Returns ['media_id' => ..., 'type' => ..., 'path' => relative].
     */
    public static function uploadForSending(array $phoneNumber, string $relativePath, string $mime): array
    {
        $absolute = Storage::path($relativePath);
        $type = self::validate($absolute, $mime);
        $tenantId = (int) $phoneNumber['tenant_id'];

        // Cache lookup: reuse an unexpired media_id for the same file
        $cached = DB::table('media_library')
            ->where('tenant_id', $tenantId)
            ->where('path', $relativePath)
            ->whereNotNull('meta_media_id')
            ->where('meta_media_expires_at', '>', now())
            ->first();
        if ($cached !== null) {
            return ['media_id' => (string) $cached['meta_media_id'], 'type' => $type, 'path' => $relativePath];
        }

        $client = CloudApiClient::forPhoneNumber($phoneNumber);
        $response = $client->uploadMedia((string) $phoneNumber['phone_number_id'], $absolute, $mime);
        $mediaId = (string) ($response['id'] ?? '');
        if ($mediaId === '') {
            throw new \RuntimeException(__('media.upload_failed', 'Media upload to WhatsApp failed.'));
        }

        $expiresAt = date('Y-m-d H:i:s', time() + ((int) config('meta.media_id_cache_days', 30)) * 86400);
        $existing = DB::table('media_library')->where('tenant_id', $tenantId)->where('path', $relativePath)->first();
        if ($existing !== null) {
            DB::table('media_library')->where('id', $existing['id'])->update([
                'meta_media_id' => $mediaId,
                'meta_media_expires_at' => $expiresAt,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('media_library')->insert([
                'tenant_id' => $tenantId,
                'name' => basename($relativePath),
                'path' => $relativePath,
                'mime' => $mime,
                'type' => in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true) ? $type : 'other',
                'size_bytes' => (int) @filesize($absolute),
                'meta_media_id' => $mediaId,
                'meta_media_expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return ['media_id' => $mediaId, 'type' => $type, 'path' => $relativePath];
    }
}
