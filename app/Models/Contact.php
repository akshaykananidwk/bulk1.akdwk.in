<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Model;
use App\Core\Sanitizer;
use App\Core\Tenant;

final class Contact extends Model
{
    protected static string $table = 'contacts';
    protected static bool $tenantScoped = true;

    /**
     * Find or create by phone within the current tenant. Returns the row.
     */
    public static function firstOrCreateByPhone(string $phone, ?string $name = null, string $source = 'inbound'): array
    {
        $phone = Sanitizer::phone($phone);
        $existing = static::query()->where('phone', $phone)->first();
        if ($existing !== null) {
            if ($name !== null && ($existing['name'] === null || $existing['name'] === '')) {
                static::updateById((int) $existing['id'], ['name' => $name]);
                $existing['name'] = $name;
            }
            return $existing;
        }

        $id = static::create([
            'phone' => $phone,
            'name' => $name,
            'source' => $source,
            'opt_in' => 1,
        ]);
        return static::find($id) ?? [];
    }

    public static function tagIds(int $contactId): array
    {
        return array_map('intval', DB::table('contact_tags')->where('contact_id', $contactId)->pluck('tag_id'));
    }

    public static function attachTag(int $contactId, int $tagId): void
    {
        $exists = DB::table('contact_tags')->where('contact_id', $contactId)->where('tag_id', $tagId)->exists();
        if (!$exists) {
            DB::table('contact_tags')->insert(['contact_id' => $contactId, 'tag_id' => $tagId]);
        }
    }

    public static function detachTag(int $contactId, int $tagId): void
    {
        DB::table('contact_tags')->where('contact_id', $contactId)->where('tag_id', $tagId)->delete();
    }

    /**
     * Custom field values keyed by field key.
     */
    public static function fieldValues(int $contactId): array
    {
        $rows = DB::table('contact_field_values')
            ->join('contact_fields', 'contact_field_values.field_id', '=', 'contact_fields.id')
            ->where('contact_field_values.contact_id', $contactId)
            ->select('contact_fields.key', 'contact_field_values.value')
            ->get();
        $values = [];
        foreach ($rows as $row) {
            $values[$row['key']] = $row['value'];
        }
        return $values;
    }

    public static function setFieldValue(int $contactId, int $fieldId, ?string $value): void
    {
        $tenantId = Tenant::id();
        $exists = DB::table('contact_field_values')
            ->where('contact_id', $contactId)
            ->where('field_id', $fieldId)
            ->exists();
        if ($exists) {
            DB::table('contact_field_values')
                ->where('contact_id', $contactId)
                ->where('field_id', $fieldId)
                ->update(['value' => $value]);
        } else {
            DB::table('contact_field_values')->insert([
                'tenant_id' => $tenantId,
                'contact_id' => $contactId,
                'field_id' => $fieldId,
                'value' => $value,
            ]);
        }
    }

    /**
     * Handle STOP/START keywords for opt-out compliance.
     * Returns true if the message was an opt-in/out command.
     */
    public static function handleOptKeywords(array $contact, string $text): bool
    {
        $normalized = strtoupper(trim($text));
        $stopWords = ['STOP', 'UNSUBSCRIBE', 'STOP ALL', 'CANCEL', 'બંધ', 'बंद'];
        $startWords = ['START', 'SUBSCRIBE', 'UNSTOP'];

        if (in_array($normalized, $stopWords, true)) {
            $log = json_decode((string) ($contact['opt_in_log'] ?? '[]'), true) ?: [];
            $log[] = ['action' => 'opt_out', 'via' => 'keyword', 'text' => $normalized, 'at' => now()];
            static::updateById((int) $contact['id'], [
                'opt_in' => 0,
                'opt_out_at' => now(),
                'opt_in_log' => json_encode($log, JSON_UNESCAPED_UNICODE),
            ]);
            return true;
        }
        if (in_array($normalized, $startWords, true)) {
            $log = json_decode((string) ($contact['opt_in_log'] ?? '[]'), true) ?: [];
            $log[] = ['action' => 'opt_in', 'via' => 'keyword', 'text' => $normalized, 'at' => now()];
            static::updateById((int) $contact['id'], [
                'opt_in' => 1,
                'opt_out_at' => null,
                'opt_in_log' => json_encode($log, JSON_UNESCAPED_UNICODE),
            ]);
            return true;
        }
        return false;
    }
}
