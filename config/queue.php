<?php

/**
 * Queue configuration.
 */
return [
    'queues' => ['default', 'messages', 'campaign', 'webhook', 'ai', 'media', 'mail', 'reports'],
    'worker' => [
        'sleep' => 1,
        'max_jobs' => 1000,
        'max_time' => 3600,
        'memory_mb' => 256,
    ],
    // Cron-only fallback: scheduler spawns a bounded worker run each minute
    'cron_fallback' => [
        'enabled' => true,
        'max_seconds' => 50,
    ],
];
