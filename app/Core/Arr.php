<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Array utilities.
 */
final class Arr
{
    /**
     * Dot-notation getter: Arr::get($data, 'contact.name').
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }
        $value = $array;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public static function set(array &$array, string $key, mixed $value): void
    {
        $ref = &$array;
        $segments = explode('.', $key);
        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $ref[$segment] = $value;
            } else {
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }
                $ref = &$ref[$segment];
            }
        }
    }

    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    public static function pluck(array $array, string $column, ?string $keyBy = null): array
    {
        $result = [];
        foreach ($array as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = self::get($item, $column);
            if ($keyBy !== null && isset($item[$keyBy])) {
                $result[$item[$keyBy]] = $value;
            } else {
                $result[] = $value;
            }
        }
        return $result;
    }

    public static function keyBy(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item) && isset($item[$key])) {
                $result[$item[$key]] = $item;
            }
        }
        return $result;
    }

    public static function groupBy(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $item) {
            if (is_array($item)) {
                $result[$item[$key] ?? ''][] = $item;
            }
        }
        return $result;
    }

    public static function flatten(array $array): array
    {
        $result = [];
        array_walk_recursive($array, function ($value) use (&$result) {
            $result[] = $value;
        });
        return $result;
    }
}
