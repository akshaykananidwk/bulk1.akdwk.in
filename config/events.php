<?php

/**
 * Event bus listeners: 'event.name' => [callable-or-class, ...]
 * Listener classes must expose handle(array $payload): void
 */
return [
    'job.failed' => [],
    'message.received' => [],
    'message.sent' => [],
    'message.failed' => [],
    'conversation.assigned' => [],
    'campaign.completed' => [],
    'tenant.registered' => [],
    'subscription.expired' => [],
    'update.available' => [],
];
