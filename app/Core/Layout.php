<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Layout helpers used inside layout templates: page titles, breadcrumbs,
 * asset stacks (per-page CSS/JS pushed from views).
 */
final class Layout
{
    private static string $title = '';
    private static array $breadcrumbs = [];
    private static array $styles = [];
    private static array $scripts = [];

    public static function title(?string $title = null): string
    {
        if ($title !== null) {
            self::$title = $title;
        }
        $appName = (string) setting('app_name', config('app.name', 'Krishna WhatsApp Cloud'));
        return self::$title !== '' ? self::$title . ' — ' . $appName : $appName;
    }

    public static function breadcrumb(string $label, ?string $url = null): void
    {
        self::$breadcrumbs[] = ['label' => $label, 'url' => $url];
    }

    public static function breadcrumbs(): array
    {
        return self::$breadcrumbs;
    }

    public static function pushStyle(string $path): void
    {
        self::$styles[$path] = $path;
    }

    public static function pushScript(string $path): void
    {
        self::$scripts[$path] = $path;
    }

    public static function styles(): array
    {
        return array_values(self::$styles);
    }

    public static function scripts(): array
    {
        return array_values(self::$scripts);
    }
}
