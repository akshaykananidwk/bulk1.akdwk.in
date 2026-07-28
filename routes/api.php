<?php

declare(strict_types=1);

/**
 * REST API v1 — API key auth (X-Api-Key header), per-key scopes and limits.
 */

use App\Core\Router;

Router::group(['prefix' => '/api/v1'], function () {
    // Messages
    Router::post('/messages', [\App\Controllers\Api\V1\MessageApiController::class, 'send'], ['apikey:messages.send']);
    Router::get('/messages/{id}', [\App\Controllers\Api\V1\MessageApiController::class, 'show'], ['apikey:messages.read'])->where('id', '\d+');

    // Contacts
    Router::get('/contacts', [\App\Controllers\Api\V1\ContactApiController::class, 'index'], ['apikey:contacts.read']);
    Router::post('/contacts', [\App\Controllers\Api\V1\ContactApiController::class, 'store'], ['apikey:contacts.write']);
    Router::get('/contacts/{id}', [\App\Controllers\Api\V1\ContactApiController::class, 'show'], ['apikey:contacts.read'])->where('id', '\d+');
    Router::put('/contacts/{id}', [\App\Controllers\Api\V1\ContactApiController::class, 'update'], ['apikey:contacts.write'])->where('id', '\d+');
    Router::delete('/contacts/{id}', [\App\Controllers\Api\V1\ContactApiController::class, 'destroy'], ['apikey:contacts.write'])->where('id', '\d+');

    // Templates
    Router::get('/templates', [\App\Controllers\Api\V1\TemplateApiController::class, 'index'], ['apikey:templates.read']);

    // Conversations
    Router::get('/conversations', [\App\Controllers\Api\V1\ConversationApiController::class, 'index'], ['apikey:conversations.read']);
    Router::get('/conversations/{id}/messages', [\App\Controllers\Api\V1\ConversationApiController::class, 'messages'], ['apikey:conversations.read'])->where('id', '\d+');
});

// API docs (interactive, vendored — no external Swagger CDN)
Router::get('/api/docs', [\App\Controllers\Api\V1\DocsController::class, 'page']);
Router::get('/api/openapi.json', [\App\Controllers\Api\V1\DocsController::class, 'spec']);
