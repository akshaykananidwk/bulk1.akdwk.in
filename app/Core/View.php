<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP view renderer with layout support.
 *
 *   View::render('tenant/dashboard', ['stats' => $stats], 'layouts/tenant');
 *
 * Views escape ALL dynamic output with e(). Sections are captured with
 * View::start('section') ... View::end() and echoed in layouts via View::yield_().
 */
final class View
{
    private static array $sections = [];
    private static array $sectionStack = [];
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * Render a view (optionally inside a layout) and return the HTML.
     */
    public static function make(string $view, array $data = [], ?string $layout = null): string
    {
        $content = self::renderFile($view, $data);

        if ($layout !== null) {
            // A view may define its content either via View::start('content')/end()
            // sections or as direct output — support both.
            if (!isset(self::$sections['content'])) {
                self::$sections['content'] = $content;
            }
            $content = self::renderFile($layout, $data);
            self::$sections = [];
        }

        return $content;
    }

    /**
     * Render and send as the HTTP response.
     */
    public static function render(string $view, array $data = [], ?string $layout = null): never
    {
        Response::html(self::make($view, $data, $layout));
    }

    private static function renderFile(string $view, array $data): string
    {
        $file = RESOURCE_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        ob_start();
        try {
            (static function (string $__file, array $__data) {
                foreach ($__data as $__key => $__value) {
                    if (preg_match('/^[a-zA-Z_]\w*$/', (string) $__key)) {
                        ${$__key} = $__value;
                    }
                }
                unset($__key, $__value, $__data);
                include $__file;
            })($file, array_merge(self::$shared, $data));
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /**
     * Include a partial from within a view.
     */
    public static function partial(string $view, array $data = []): void
    {
        echo self::renderFile($view, $data);
    }

    // -- Sections ------------------------------------------------------------

    public static function start(string $section): void
    {
        self::$sectionStack[] = $section;
        ob_start();
    }

    public static function end(): void
    {
        $section = array_pop(self::$sectionStack);
        if ($section !== null) {
            self::$sections[$section] = (string) ob_get_clean();
        }
    }

    /**
     * Echo a captured section ("yield" is reserved).
     */
    public static function yield_(string $section, string $default = ''): void
    {
        echo self::$sections[$section] ?? $default;
    }

    public static function hasSection(string $section): bool
    {
        return isset(self::$sections[$section]);
    }
}
