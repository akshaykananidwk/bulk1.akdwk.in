<?php

declare(strict_types=1);

namespace Installer;

/**
 * Server requirement checks for the installer (§11 step 2).
 */
final class RequirementChecker
{
    public const MIN_PHP = '8.3.0';

    public const REQUIRED_EXTENSIONS = [
        'pdo_mysql', 'curl', 'mbstring', 'openssl', 'zip', 'gd',
        'fileinfo', 'json', 'session',
    ];

    public const RECOMMENDED_EXTENSIONS = ['bcmath', 'intl', 'xml', 'iconv'];

    /**
     * @return array<int, array{label:string, ok:bool, critical:bool, hint:string, value:string}>
     */
    public static function all(string $rootPath): array
    {
        $checks = [];

        $checks[] = [
            'label' => 'PHP version ≥ ' . self::MIN_PHP,
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'critical' => true,
            'value' => PHP_VERSION,
            'hint' => 'Upgrade PHP in aaPanel → App Store → PHP 8.3, then set the site PHP version.',
        ];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $checks[] = [
                'label' => 'Extension: ' . $extension,
                'ok' => extension_loaded($extension),
                'critical' => true,
                'value' => extension_loaded($extension) ? 'loaded' : 'missing',
                'hint' => 'Install via aaPanel → PHP 8.3 → Install extensions → ' . $extension . '.',
            ];
        }

        foreach (self::RECOMMENDED_EXTENSIONS as $extension) {
            $checks[] = [
                'label' => 'Extension (recommended): ' . $extension,
                'ok' => extension_loaded($extension),
                'critical' => false,
                'value' => extension_loaded($extension) ? 'loaded' : 'missing',
                'hint' => 'Optional but recommended for Excel export and localisation.',
            ];
        }

        $memory = self::iniBytes('memory_limit');
        $checks[] = [
            'label' => 'memory_limit ≥ 256M',
            'ok' => $memory === -1 || $memory >= 256 * 1048576,
            'critical' => true,
            'value' => (string) ini_get('memory_limit'),
            'hint' => 'Set memory_limit = 256M in PHP configuration.',
        ];

        $maxExecution = (int) ini_get('max_execution_time');
        $checks[] = [
            'label' => 'max_execution_time ≥ 120',
            'ok' => $maxExecution === 0 || $maxExecution >= 120,
            'critical' => false,
            'value' => (string) $maxExecution,
            'hint' => 'Set max_execution_time = 120 (CLI cron jobs are unaffected).',
        ];

        $uploadMax = self::iniBytes('upload_max_filesize');
        $checks[] = [
            'label' => 'upload_max_filesize ≥ 100M',
            'ok' => $uploadMax >= 100 * 1048576,
            'critical' => false,
            'value' => (string) ini_get('upload_max_filesize'),
            'hint' => 'Needed for 100MB WhatsApp document uploads. Set in PHP config.',
        ];

        $postMax = self::iniBytes('post_max_size');
        $checks[] = [
            'label' => 'post_max_size ≥ 100M',
            'ok' => $postMax === -1 || $postMax >= 100 * 1048576,
            'critical' => false,
            'value' => (string) ini_get('post_max_size'),
            'hint' => 'Set post_max_size = 100M (must be ≥ upload_max_filesize).',
        ];

        $checks[] = [
            'label' => 'Outbound HTTPS (cURL or allow_url_fopen)',
            'ok' => function_exists('curl_init') || filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN),
            'critical' => true,
            'value' => function_exists('curl_init') ? 'cURL available' : 'missing',
            'hint' => 'Meta API calls require cURL.',
        ];

        foreach ([
            ['config/', $rootPath . '/config'],
            ['storage/', $rootPath . '/storage'],
            ['uploads/', $rootPath . '/uploads'],
            ['root (installed.lock)', $rootPath],
        ] as [$label, $path]) {
            $writable = is_dir($path) ? is_writable($path) : is_writable(dirname($path));
            $checks[] = [
                'label' => 'Writable: ' . $label,
                'ok' => $writable,
                'critical' => true,
                'value' => $writable ? 'writable' : 'not writable',
                'hint' => 'chown -R www:www ' . $path . ' && chmod -R 755 ' . $path,
            ];
        }

        return $checks;
    }

    public static function allCriticalPass(string $rootPath): bool
    {
        foreach (self::all($rootPath) as $check) {
            if ($check['critical'] && !$check['ok']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Live mod_rewrite test: fetch /__rewrite_test which only resolves
     * through the rewrite rule.
     */
    public static function rewriteWorks(): ?bool
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        if ($host === null || !function_exists('curl_init')) {
            return null; // unknown
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $ch = curl_init($scheme . '://' . $host . '/__rewrite_test');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false, // self-signed during setup is fine for a loopback probe
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body === false) {
            return null;
        }
        return trim((string) $body) === 'REWRITE_OK';
    }

    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;
        return (int) match ($unit) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
