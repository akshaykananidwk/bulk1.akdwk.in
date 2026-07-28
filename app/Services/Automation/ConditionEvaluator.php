<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Core\Arr;

/**
 * Evaluates flow condition groups with AND/OR and 20+ operators.
 * Condition: {"variable":"contact.name","operator":"contains","value":"x"}
 */
final class ConditionEvaluator
{
    public static function evaluate(array $conditions, string $match, array $variables): bool
    {
        if (empty($conditions)) {
            return true;
        }
        $results = array_map(
            fn (array $condition) => self::one($condition, $variables),
            array_values(array_filter($conditions, 'is_array'))
        );
        return $match === 'any' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }

    private static function one(array $condition, array $variables): bool
    {
        $left = Arr::get($variables, (string) ($condition['variable'] ?? ''), null);
        $operator = (string) ($condition['operator'] ?? 'equals');
        $right = $condition['value'] ?? '';

        $leftString = is_scalar($left) ? (string) $left : json_encode($left);
        $rightString = is_scalar($right) ? (string) $right : json_encode($right);

        return match ($operator) {
            'equals' => mb_strtolower(trim((string) $leftString)) === mb_strtolower(trim($rightString)),
            'not_equals' => mb_strtolower(trim((string) $leftString)) !== mb_strtolower(trim($rightString)),
            'contains' => $leftString !== null && mb_stripos($leftString, $rightString) !== false,
            'not_contains' => $leftString === null || mb_stripos($leftString, $rightString) === false,
            'starts_with' => $leftString !== null && str_starts_with(mb_strtolower($leftString), mb_strtolower($rightString)),
            'ends_with' => $leftString !== null && str_ends_with(mb_strtolower($leftString), mb_strtolower($rightString)),
            'greater_than' => is_numeric($leftString) && (float) $leftString > (float) $rightString,
            'greater_or_equal' => is_numeric($leftString) && (float) $leftString >= (float) $rightString,
            'less_than' => is_numeric($leftString) && (float) $leftString < (float) $rightString,
            'less_or_equal' => is_numeric($leftString) && (float) $leftString <= (float) $rightString,
            'is_empty' => $left === null || $leftString === '' || $left === [],
            'is_not_empty' => !($left === null || $leftString === '' || $left === []),
            'is_number' => is_numeric($leftString),
            'is_email' => filter_var((string) $leftString, FILTER_VALIDATE_EMAIL) !== false,
            'is_phone' => preg_match('/^\+?\d{8,15}$/', preg_replace('/[\s\-()]/', '', (string) $leftString) ?? '') === 1,
            'matches_regex' => @preg_match('/' . str_replace('/', '\/', $rightString) . '/iu', (string) $leftString) === 1,
            'in_list' => in_array(mb_strtolower(trim((string) $leftString)), array_map(fn ($item) => mb_strtolower(trim((string) $item)), explode(',', $rightString)), true),
            'not_in_list' => !in_array(mb_strtolower(trim((string) $leftString)), array_map(fn ($item) => mb_strtolower(trim((string) $item)), explode(',', $rightString)), true),
            'before_time' => date('H:i') < $rightString,
            'after_time' => date('H:i') > $rightString,
            'day_of_week' => in_array(strtolower(date('D')), array_map('strtolower', array_map('trim', explode(',', $rightString))), true),
            default => false,
        };
    }
}
