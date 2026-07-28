<?php

declare(strict_types=1);

namespace App\Services\Meta;

use App\Core\DB;
use App\Core\Logger;

/**
 * Template CRUD synced with Meta + variable mapping resolution.
 */
final class TemplateService
{
    /**
     * Pull all templates for a WABA from Meta and upsert locally.
     * Returns count synced.
     */
    public static function syncFromMeta(array $waba): int
    {
        $client = new CloudApiClient($waba);
        $count = 0;
        $after = null;

        do {
            $page = $client->listTemplates($after);
            foreach ((array) ($page['data'] ?? []) as $template) {
                self::upsert($waba, $template);
                $count++;
            }
            $after = $page['paging']['cursors']['after'] ?? null;
            $hasNext = isset($page['paging']['next']);
        } while ($hasNext && $after !== null);

        Logger::channel('meta')->info('Templates synced', ['waba' => $waba['waba_id'], 'count' => $count]);
        return $count;
    }

    private static function upsert(array $waba, array $template): void
    {
        $name = (string) ($template['name'] ?? '');
        $language = (string) ($template['language'] ?? 'en');
        if ($name === '') {
            return;
        }

        $existing = DB::table('templates')
            ->where('waba_account_id', $waba['id'])
            ->where('name', $name)
            ->where('language', $language)
            ->first();

        $status = strtoupper((string) ($template['status'] ?? 'PENDING'));
        $allowed = ['APPROVED', 'PENDING', 'REJECTED', 'PAUSED', 'DISABLED'];
        if (!in_array($status, $allowed, true)) {
            $status = 'PENDING';
        }

        $row = [
            'meta_template_id' => (string) ($template['id'] ?? ''),
            'category' => in_array($template['category'] ?? '', ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true)
                ? $template['category'] : 'MARKETING',
            'status' => $status,
            'rejected_reason' => isset($template['rejected_reason']) && $template['rejected_reason'] !== 'NONE'
                ? (string) $template['rejected_reason'] : null,
            'components' => json_encode($template['components'] ?? [], JSON_UNESCAPED_UNICODE),
            'last_synced_at' => now(),
            'updated_at' => now(),
        ];

        if ($existing !== null) {
            // Notify on status transition
            if ($existing['status'] !== $status && in_array($status, ['APPROVED', 'REJECTED'], true)) {
                DB::table('notifications')->insert([
                    'tenant_id' => (int) $waba['tenant_id'],
                    'type' => 'template.status',
                    'title' => __('templates.status_changed', 'Template status changed'),
                    'body' => $name . ' → ' . $status,
                    'link' => '/tenant/templates',
                    'created_at' => now(),
                ]);
            }
            // Keep a version snapshot when components changed
            if ($existing['components'] !== $row['components']) {
                DB::table('template_versions')->insert([
                    'template_id' => (int) $existing['id'],
                    'components' => $existing['components'],
                    'status' => (string) $existing['status'],
                    'created_at' => now(),
                ]);
            }
            DB::table('templates')->where('id', $existing['id'])->update($row);
        } else {
            DB::table('templates')->insert($row + [
                'tenant_id' => (int) $waba['tenant_id'],
                'waba_account_id' => (int) $waba['id'],
                'name' => $name,
                'language' => $language,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Submit a locally-drafted template to Meta for approval.
     */
    public static function submit(array $waba, array $template): array
    {
        $client = new CloudApiClient($waba);
        $response = $client->createTemplate([
            'name' => (string) $template['name'],
            'language' => (string) $template['language'],
            'category' => (string) $template['category'],
            'components' => json_decode((string) $template['components'], true) ?: [],
        ]);

        DB::table('templates')->where('id', $template['id'])->update([
            'meta_template_id' => (string) ($response['id'] ?? ''),
            'status' => strtoupper((string) ($response['status'] ?? 'PENDING')),
            'updated_at' => now(),
        ]);

        return $response;
    }

    /**
     * Build the Graph "components" array for sending an approved template,
     * resolving {{n}} params from the mapping + contact data.
     *
     * $mapping: [ '1' => ['source' => 'contact.name'|'static'|'field.city', 'value' => '...'], ... ]
     */
    public static function buildSendComponents(array $template, array $mapping, array $contact, array $fieldValues = []): array
    {
        $components = json_decode((string) ($template['components'] ?? '[]'), true) ?: [];
        $out = [];

        foreach ($components as $component) {
            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type === 'HEADER') {
                $format = strtoupper((string) ($component['format'] ?? 'TEXT'));
                if ($format === 'TEXT') {
                    $params = self::paramsFor((string) ($component['text'] ?? ''), $mapping, $contact, $fieldValues, 'header');
                    if ($params) {
                        $out[] = ['type' => 'header', 'parameters' => $params];
                    }
                } elseif (in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
                    $key = strtolower($format);
                    $mediaRef = $mapping['header_media'] ?? null;
                    if ($mediaRef !== null) {
                        $parameter = ['type' => $key];
                        $parameter[$key] = isset($mediaRef['media_id'])
                            ? ['id' => (string) $mediaRef['media_id']]
                            : ['link' => (string) ($mediaRef['link'] ?? '')];
                        $out[] = ['type' => 'header', 'parameters' => [$parameter]];
                    }
                }
            }

            if ($type === 'BODY') {
                $params = self::paramsFor((string) ($component['text'] ?? ''), $mapping, $contact, $fieldValues, 'body');
                if ($params) {
                    $out[] = ['type' => 'body', 'parameters' => $params];
                }
            }

            if ($type === 'BUTTONS') {
                foreach ((array) ($component['buttons'] ?? []) as $index => $button) {
                    $buttonType = strtoupper((string) ($button['type'] ?? ''));
                    if ($buttonType === 'URL' && str_contains((string) ($button['url'] ?? ''), '{{')) {
                        $value = self::resolveMapping($mapping['button_' . $index] ?? ['source' => 'static', 'value' => ''], $contact, $fieldValues);
                        $out[] = [
                            'type' => 'button', 'sub_type' => 'url', 'index' => (string) $index,
                            'parameters' => [['type' => 'text', 'text' => $value]],
                        ];
                    }
                    if ($buttonType === 'COPY_CODE') {
                        $value = self::resolveMapping($mapping['button_' . $index] ?? ['source' => 'static', 'value' => ''], $contact, $fieldValues);
                        $out[] = [
                            'type' => 'button', 'sub_type' => 'copy_code', 'index' => (string) $index,
                            'parameters' => [['type' => 'coupon_code', 'coupon_code' => $value]],
                        ];
                    }
                }
            }
        }

        return $out;
    }

    private static function paramsFor(string $text, array $mapping, array $contact, array $fieldValues, string $section): array
    {
        preg_match_all('/\{\{(\d+)\}\}/', $text, $matches);
        $params = [];
        foreach ($matches[1] as $number) {
            $key = $section === 'header' ? 'header_' . $number : (string) $number;
            $map = $mapping[$key] ?? $mapping[(string) $number] ?? ['source' => 'static', 'value' => ''];
            $params[] = ['type' => 'text', 'text' => self::resolveMapping($map, $contact, $fieldValues)];
        }
        return $params;
    }

    private static function resolveMapping(array $map, array $contact, array $fieldValues): string
    {
        $source = (string) ($map['source'] ?? 'static');
        $value = (string) ($map['value'] ?? '');

        if ($source === 'static') {
            return \App\Core\Str::spintax($value);
        }
        if (str_starts_with($source, 'contact.')) {
            $key = substr($source, 8);
            return (string) ($contact[$key] ?? $value);
        }
        if (str_starts_with($source, 'field.')) {
            $key = substr($source, 6);
            return (string) ($fieldValues[$key] ?? $value);
        }
        return $value;
    }
}
