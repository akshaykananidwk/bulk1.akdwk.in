<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Integration\WebhookDispatcher;

/**
 * Delivers one outbound webhook; queue retries with backoff on failure.
 */
final class DeliverWebhookJob implements JobInterface
{
    public function handle(array $payload): void
    {
        WebhookDispatcher::deliver($payload);
    }
}
