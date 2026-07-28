<?php

declare(strict_types=1);

namespace App\Core;

/**
 * i18n string repository. Language files live in lang/{code}.php and return
 * nested arrays; keys are dot-notated: __('inbox.assign_to_me').
 */
final class Lang
{
    private static array $lines = [];
    private static ?string $locale = null;

    public static function locale(): string
    {
        if (self::$locale !== null) {
            return self::$locale;
        }

        $locale = null;
        if (PHP_SAPI !== 'cli') {
            $locale = $_SESSION['locale'] ?? null;
        }
        if ($locale === null) {
            try {
                $user = Auth::user();
                $locale = $user['locale'] ?? null;
            } catch (\Throwable) {
                $locale = null;
            }
        }
        $locale = $locale ?? (string) config('app.locale', 'en');
        self::$locale = preg_replace('/[^a-z_]/i', '', (string) $locale) ?: 'en';
        return self::$locale;
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = preg_replace('/[^a-z_]/i', '', $locale) ?: 'en';
        if (PHP_SAPI !== 'cli') {
            $_SESSION['locale'] = self::$locale;
        }
    }

    /**
     * Translate a key. Falls back to English, then to $default (or the key).
     */
    public static function get(string $key, ?string $default = null, array $replace = []): string
    {
        $locale = self::locale();
        $value = self::lookup($locale, $key) ?? self::lookup('en', $key) ?? $default ?? $key;

        foreach ($replace as $search => $replacement) {
            $value = str_replace(':' . $search, (string) $replacement, $value);
        }
        return $value;
    }

    private static function lookup(string $locale, string $key): ?string
    {
        if (!isset(self::$lines[$locale])) {
            $file = ROOT_PATH . '/lang/' . $locale . '.php';
            self::$lines[$locale] = is_file($file) ? (array) (require $file) : [];
        }

        $segments = explode('.', $key);
        $value = self::$lines[$locale];
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return null;
            }
        }
        return is_string($value) ? $value : null;
    }

    public static function available(): array
    {
        $languages = [];
        foreach (glob(ROOT_PATH . '/lang/*.php') ?: [] as $file) {
            $code = basename($file, '.php');
            $languages[$code] = match ($code) {
                'en' => 'English',
                'gu' => 'ગુજરાતી',
                'hi' => 'हिन्दी',
                default => strtoupper($code),
            };
        }
        return $languages;
    }
}
