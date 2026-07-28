<?php

declare(strict_types=1);

namespace App\Core;

/**
 * String utilities.
 */
final class Str
{
    public static function random(int $length = 16): string
    {
        $pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        $max = strlen($pool) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $pool[random_int(0, $max)];
        }
        return $result;
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function slug(string $value): string
    {
        return Sanitizer::slug($value);
    }

    public static function limit(string $value, int $limit = 100, string $end = '…'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }
        return rtrim(mb_substr($value, 0, $limit)) . $end;
    }

    public static function snake(string $value): string
    {
        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        $value = preg_replace('/(.)(?=[A-Z])/u', '$1_', $value) ?? $value;
        return mb_strtolower($value);
    }

    public static function camel(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = str_replace(' ', '', ucwords($value));
        return lcfirst($value);
    }

    public static function studly(string $value): string
    {
        return ucfirst(self::camel($value));
    }

    public static function mask(string $value, int $visibleStart = 2, int $visibleEnd = 2): string
    {
        $length = mb_strlen($value);
        if ($length <= $visibleStart + $visibleEnd) {
            return str_repeat('•', $length);
        }
        return mb_substr($value, 0, $visibleStart)
            . str_repeat('•', $length - $visibleStart - $visibleEnd)
            . mb_substr($value, -$visibleEnd);
    }

    /**
     * Render {{variable}} placeholders in message templates.
     * Supports dot paths: {{contact.name}}.
     */
    public static function interpolate(string $template, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function ($m) use ($variables) {
            $value = Arr::get($variables, $m[1]);
            return $value === null ? $m[0] : (string) (is_scalar($value) ? $value : json_encode($value));
        }, $template) ?? $template;
    }

    /**
     * Spintax: "Hello {Hi|Hey|Namaste}" -> random pick.
     */
    public static function spintax(string $text): string
    {
        while (preg_match('/\{([^{}]+)\}/', $text, $m)) {
            if (!str_contains($m[1], '|')) {
                break;
            }
            $options = explode('|', $m[1]);
            $text = preg_replace('/' . preg_quote($m[0], '/') . '/', $options[array_rand($options)], $text, 1) ?? $text;
        }
        return $text;
    }

    public static function initials(string $name, int $max = 2): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $initials = '';
        foreach (array_slice($words, 0, $max) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }
        return $initials !== '' ? $initials : '?';
    }

    public static function humanBytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $bytes = (float) $bytes;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $i > 1 ? 2 : 0) . ' ' . $units[$i];
    }
}
