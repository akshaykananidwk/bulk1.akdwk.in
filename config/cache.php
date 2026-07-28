<?php

/**
 * Cache configuration. Redis is OPTIONAL — file is the default driver and
 * the platform is fully functional without Redis.
 */
return [
    'driver' => 'file', // file | apcu | redis
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
    ],
];
