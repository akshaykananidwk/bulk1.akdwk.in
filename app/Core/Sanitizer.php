<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Input sanitisation helpers. Output escaping is handled by e() in views;
 * this class normalises data BEFORE storage where needed.
 */
final class Sanitizer
{
    /**
     * Normalise a phone number to E.164-ish digits (with country code, no +).
     */
    public static function phone(string $phone, string $defaultCountryCode = '91'): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        // Strip leading zeros
        $digits = ltrim($digits, '0');
        // 10-digit local numbers get the default country code
        if (strlen($digits) === 10) {
            $digits = $defaultCountryCode . $digits;
        }
        return substr($digits, 0, 15);
    }

    /**
     * Strip all HTML tags and control characters from a plain-text field.
     */
    public static function text(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        return trim($value);
    }

    /**
     * Very small allow-list HTML cleaner for rich-text notes.
     */
    public static function richText(string $html): string
    {
        $allowed = '<b><strong><i><em><u><s><br><p><ul><ol><li><a><blockquote><code><pre>';
        $html = strip_tags($html, $allowed);
        // Neutralise javascript: URLs and event handlers
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/href\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*\1/i', 'href="#"', $html) ?? $html;
        return $html;
    }

    /**
     * Safe filename (no traversal, no weird chars).
     */
    public static function filename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\w.\-]+/u', '_', $name) ?? $name;
        $name = preg_replace('/\.{2,}/', '.', $name) ?? $name;
        return trim($name, '._') ?: 'file';
    }

    /**
     * URL slug.
     */
    public static function slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        return trim($value, '-');
    }
}
