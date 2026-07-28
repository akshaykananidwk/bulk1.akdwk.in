<?php

declare(strict_types=1);

namespace App\Services\Campaign;

use App\Core\DB;

/**
 * Dynamic segments: rule-based contact matching, auto-refreshing.
 * Rules JSON: {"match":"all|any","conditions":[{"field","operator","value"}]}
 * Fields: name, phone, email, lifecycle_stage, lead_score, source, opt_in,
 * last_message_at, created_at, tag (special), field.<key> (custom field).
 */
final class SegmentResolver
{
    public static function contactIds(array $segment): array
    {
        $rules = json_decode((string) ($segment['rules'] ?? '{}'), true) ?: [];
        $conditions = (array) ($rules['conditions'] ?? []);
        $matchAll = ($rules['match'] ?? 'all') !== 'any';
        $tenantId = (int) $segment['tenant_id'];

        $query = DB::table('contacts')->where('tenant_id', $tenantId)->select('id');

        $apply = function ($builder, array $condition, string $boolean) use ($tenantId): void {
            $field = (string) ($condition['field'] ?? '');
            $operator = (string) ($condition['operator'] ?? 'equals');
            $value = $condition['value'] ?? '';

            // Tag membership
            if ($field === 'tag') {
                $contactIds = array_map('intval', DB::table('contact_tags')->where('tag_id', (int) $value)->pluck('contact_id'));
                $builder->whereIn('id', $contactIds ?: [0], $boolean, $operator === 'not_equals');
                return;
            }
            // Custom field
            if (str_starts_with($field, 'field.')) {
                $key = substr($field, 6);
                $fieldRow = DB::table('contact_fields')->where('tenant_id', $tenantId)->where('key', $key)->first();
                if ($fieldRow === null) {
                    return;
                }
                $sub = DB::table('contact_field_values')->where('field_id', (int) $fieldRow['id']);
                match ($operator) {
                    'contains' => $sub->whereLike('value', '%' . $value . '%'),
                    'not_equals' => $sub->where('value', '!=', (string) $value),
                    'greater_than' => $sub->where('value', '>', (string) $value),
                    'less_than' => $sub->where('value', '<', (string) $value),
                    default => $sub->where('value', (string) $value),
                };
                $builder->whereIn('id', array_map('intval', $sub->pluck('contact_id')) ?: [0], $boolean);
                return;
            }

            $allowed = ['name', 'phone', 'email', 'lifecycle_stage', 'lead_score', 'source', 'opt_in', 'last_message_at', 'created_at', 'country_code', 'language'];
            if (!in_array($field, $allowed, true)) {
                return;
            }

            switch ($operator) {
                case 'contains':
                    $builder->whereLike($field, '%' . $value . '%', $boolean);
                    break;
                case 'not_equals':
                    $builder->where($field, '!=', $value, $boolean);
                    break;
                case 'greater_than':
                    $builder->where($field, '>', $value, $boolean);
                    break;
                case 'less_than':
                    $builder->where($field, '<', $value, $boolean);
                    break;
                case 'is_empty':
                    $builder->whereNull($field, $boolean);
                    break;
                case 'is_not_empty':
                    $builder->whereNotNull($field, $boolean);
                    break;
                case 'days_ago_more_than':
                    $builder->where($field, '<', date('Y-m-d H:i:s', time() - ((int) $value) * 86400), $boolean);
                    break;
                case 'days_ago_less_than':
                    $builder->where($field, '>', date('Y-m-d H:i:s', time() - ((int) $value) * 86400), $boolean);
                    break;
                default:
                    $builder->where($field, '=', $value, $boolean);
            }
        };

        if ($conditions) {
            $query->whereGroup(function ($group) use ($conditions, $matchAll, $apply) {
                foreach ($conditions as $i => $condition) {
                    $apply($group, (array) $condition, $i === 0 ? 'AND' : ($matchAll ? 'AND' : 'OR'));
                }
            });
        }

        $ids = array_map('intval', $query->pluck('id'));

        DB::table('segments')->where('id', $segment['id'])->update([
            'contact_count' => count($ids),
            'refreshed_at' => now(),
        ]);

        return $ids;
    }
}
