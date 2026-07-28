<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configuration repository.
 *
 * Loads config/*.php files lazily. The installer-generated config/config.php
 * (DB credentials, APP_KEY, APP_URL...) is merged on top so it always wins.
 */
final class Config
{
    private static array $items = [];
    private static array $loaded = [];
    private static array $installerConfig = [];
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $file = CONFIG_PATH . '/config.php';
        if (is_file($file)) {
            $data = require $file;
            if (is_array($data)) {
                self::$installerConfig = $data;
            }
        }
    }

    /**
     * Get a config value using dot notation: config('app.name'), config('db.host').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $group = array_shift($segments);

        self::loadGroup($group);

        $value = self::$items[$group] ?? null;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value ?? $default;
    }

    /**
     * Set a runtime config value (does not persist).
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $group = array_shift($segments);
        self::loadGroup($group);

        if (empty($segments)) {
            self::$items[$group] = $value;
            return;
        }

        $ref = &self::$items[$group];
        if (!is_array($ref)) {
            $ref = [];
        }
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

    public static function all(string $group): array
    {
        self::loadGroup($group);
        return is_array(self::$items[$group] ?? null) ? self::$items[$group] : [];
    }

    /**
     * True when the installer has generated config/config.php.
     */
    public static function isInstalled(): bool
    {
        return !empty(self::$installerConfig) && is_file(ROOT_PATH . '/installed.lock');
    }

    private static function loadGroup(string $group): void
    {
        if (isset(self::$loaded[$group])) {
            return;
        }
        self::$loaded[$group] = true;

        $base = [];
        $file = CONFIG_PATH . '/' . $group . '.php';
        if (is_file($file) && $group !== 'config') {
            $data = require $file;
            if (is_array($data)) {
                $base = $data;
            }
        }

        // Installer config overrides shipped defaults for its groups.
        if (isset(self::$installerConfig[$group]) && is_array(self::$installerConfig[$group])) {
            $base = array_replace_recursive($base, self::$installerConfig[$group]);
        }

        self::$items[$group] = $base;
    }
}
