<?php

/**
 * Meta WhatsApp Cloud API defaults. The live values (app id/secret, tokens)
 * are stored encrypted in the settings/meta_apps tables via the admin panel.
 */
return [
    'graph_url' => 'https://graph.facebook.com',
    'api_version' => 'v21.0',
    'webhook_verify_token' => '', // set via admin panel; stored in settings
    'media_limits' => [
        // bytes
        'image' => 5 * 1024 * 1024,
        'video' => 16 * 1024 * 1024,
        'audio' => 16 * 1024 * 1024,
        'document' => 100 * 1024 * 1024,
        'sticker_static' => 100 * 1024,
        'sticker_animated' => 500 * 1024,
    ],
    'media_id_cache_days' => 30,
    'session_window_hours' => 24,
];
