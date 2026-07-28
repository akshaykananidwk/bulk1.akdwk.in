<?php

/**
 * Application defaults. Installer-generated config/config.php overrides
 * the 'app' group values (name, url, key, timezone, locale, debug).
 */
return [
    'name' => 'Krishna WhatsApp Cloud',
    'url' => '',
    'key' => '',
    'env' => 'production',
    'debug' => false,
    'timezone' => 'Asia/Kolkata',
    'locale' => 'en',
    'currency' => 'INR',
    'version' => '1.0.0',
    'license_check' => false,
    'trusted_proxies' => [],
    'sse' => [
        'max_connections_per_tenant' => 25,
        'loop_seconds' => 30,
        'poll_interval_ms' => 1500,
    ],
];
