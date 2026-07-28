<?php

declare(strict_types=1);

/**
 * Webhook receivers — NO auth/CSRF (signature-verified instead).
 */

use App\Core\Router;

Router::get('/webhook/meta', [\App\Controllers\Webhook\MetaWebhookController::class, 'verify']);
Router::post('/webhook/meta', [\App\Controllers\Webhook\MetaWebhookController::class, 'receive']);

Router::post('/webhook/payment/{gateway}', [\App\Controllers\Webhook\PaymentWebhookController::class, 'receive'])
    ->where('gateway', '[a-z_]+');

Router::post('/webhook/ecom/{store}', [\App\Controllers\Webhook\EcomWebhookController::class, 'receive'])
    ->where('store', '\d+');
