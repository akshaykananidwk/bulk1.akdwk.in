<?php

declare(strict_types=1);

/**
 * Tenant panel routes. All wrapped in auth + tenant + csrf middleware.
 */

use App\Core\Router;

Router::group(['prefix' => '/tenant', 'middleware' => ['auth', 'tenant', 'csrf'], 'name' => 'tenant.'], function () {
    Router::get('/', [\App\Controllers\Tenant\DashboardController::class, 'index'])->name('dashboard');
    Router::get('/dashboard', [\App\Controllers\Tenant\DashboardController::class, 'index']);

    // WhatsApp connection
    Router::get('/whatsapp', [\App\Controllers\Tenant\WhatsAppController::class, 'index'], ['permission:whatsapp.view'])->name('whatsapp');
    Router::post('/whatsapp/manual-connect', [\App\Controllers\Tenant\WhatsAppController::class, 'manualConnect'], ['permission:whatsapp.connect']);
    Router::post('/whatsapp/embedded-callback', [\App\Controllers\Tenant\WhatsAppController::class, 'embeddedCallback'], ['permission:whatsapp.connect']);
    Router::post('/whatsapp/{id}/refresh', [\App\Controllers\Tenant\WhatsAppController::class, 'refresh'], ['permission:whatsapp.numbers'])->where('id', '\d+');
    Router::post('/whatsapp/{id}/disconnect', [\App\Controllers\Tenant\WhatsAppController::class, 'disconnect'], ['permission:whatsapp.connect'])->where('id', '\d+');

    // Inbox
    Router::get('/inbox', [\App\Controllers\Tenant\InboxController::class, 'index'], ['permission:inbox.view'])->name('inbox');
    Router::get('/inbox/conversations', [\App\Controllers\Tenant\InboxController::class, 'list'], ['permission:inbox.view']);
    Router::get('/inbox/{id}', [\App\Controllers\Tenant\InboxController::class, 'show'], ['permission:inbox.view'])->where('id', '\d+')->name('inbox.show');
    Router::get('/inbox/{id}/messages', [\App\Controllers\Tenant\InboxController::class, 'messages'], ['permission:inbox.view'])->where('id', '\d+');
    Router::post('/inbox/{id}/send', [\App\Controllers\Tenant\InboxController::class, 'send'], ['permission:inbox.send'])->where('id', '\d+');
    Router::post('/inbox/{id}/note', [\App\Controllers\Tenant\InboxController::class, 'addNote'], ['permission:inbox.notes'])->where('id', '\d+');
    Router::post('/inbox/{id}/assign', [\App\Controllers\Tenant\InboxController::class, 'assign'], ['permission:inbox.assign'])->where('id', '\d+');
    Router::post('/inbox/{id}/status', [\App\Controllers\Tenant\InboxController::class, 'setStatus'], ['permission:inbox.view'])->where('id', '\d+');
    Router::post('/inbox/{id}/read', [\App\Controllers\Tenant\InboxController::class, 'markRead'], ['permission:inbox.view'])->where('id', '\d+');
    Router::post('/inbox/{id}/typing', [\App\Controllers\Tenant\InboxController::class, 'typing'], ['permission:inbox.send'])->where('id', '\d+');

    // Contacts
    Router::get('/contacts', [\App\Controllers\Tenant\ContactController::class, 'index'], ['permission:contacts.view'])->name('contacts');
    Router::get('/contacts/export', [\App\Controllers\Tenant\ContactController::class, 'export'], ['permission:contacts.export']);
    Router::get('/contacts/{id}', [\App\Controllers\Tenant\ContactController::class, 'show'], ['permission:contacts.view'])->where('id', '\d+')->name('contacts.show');
    Router::post('/contacts', [\App\Controllers\Tenant\ContactController::class, 'store'], ['permission:contacts.create']);
    Router::post('/contacts/import', [\App\Controllers\Tenant\ContactController::class, 'import'], ['permission:contacts.import']);
    Router::post('/contacts/{id}', [\App\Controllers\Tenant\ContactController::class, 'update'], ['permission:contacts.edit'])->where('id', '\d+');
    Router::post('/contacts/{id}/delete', [\App\Controllers\Tenant\ContactController::class, 'destroy'], ['permission:contacts.delete'])->where('id', '\d+');
    Router::post('/contacts/{id}/tags', [\App\Controllers\Tenant\ContactController::class, 'syncTags'], ['permission:contacts.edit'])->where('id', '\d+');

    // Templates
    Router::get('/templates', [\App\Controllers\Tenant\TemplateController::class, 'index'], ['permission:templates.view'])->name('templates');
    Router::get('/templates/create', [\App\Controllers\Tenant\TemplateController::class, 'create'], ['permission:templates.create']);
    Router::post('/templates', [\App\Controllers\Tenant\TemplateController::class, 'store'], ['permission:templates.create']);
    Router::post('/templates/sync', [\App\Controllers\Tenant\TemplateController::class, 'sync'], ['permission:templates.sync']);
    Router::post('/templates/{id}/delete', [\App\Controllers\Tenant\TemplateController::class, 'destroy'], ['permission:templates.delete'])->where('id', '\d+');

    // Campaigns
    Router::get('/campaigns', [\App\Controllers\Tenant\CampaignController::class, 'index'], ['permission:campaigns.view'])->name('campaigns');
    Router::get('/campaigns/create', [\App\Controllers\Tenant\CampaignController::class, 'create'], ['permission:campaigns.create']);
    Router::post('/campaigns', [\App\Controllers\Tenant\CampaignController::class, 'store'], ['permission:campaigns.create']);
    Router::get('/campaigns/{id}', [\App\Controllers\Tenant\CampaignController::class, 'show'], ['permission:campaigns.view'])->where('id', '\d+')->name('campaigns.show');
    Router::post('/campaigns/{id}/launch', [\App\Controllers\Tenant\CampaignController::class, 'launch'], ['permission:campaigns.send'])->where('id', '\d+');
    Router::post('/campaigns/{id}/pause', [\App\Controllers\Tenant\CampaignController::class, 'pause'], ['permission:campaigns.control'])->where('id', '\d+');
    Router::post('/campaigns/{id}/resume', [\App\Controllers\Tenant\CampaignController::class, 'resume'], ['permission:campaigns.control'])->where('id', '\d+');
    Router::post('/campaigns/{id}/cancel', [\App\Controllers\Tenant\CampaignController::class, 'cancel'], ['permission:campaigns.control'])->where('id', '\d+');
    Router::get('/campaigns/{id}/progress', [\App\Controllers\Tenant\CampaignController::class, 'progress'], ['permission:campaigns.view'])->where('id', '\d+');

    // Team
    Router::get('/team', [\App\Controllers\Tenant\TeamController::class, 'index'], ['permission:team.view'])->name('team');
    Router::post('/team/invite', [\App\Controllers\Tenant\TeamController::class, 'invite'], ['permission:team.manage']);
    Router::post('/team/{id}/update', [\App\Controllers\Tenant\TeamController::class, 'update'], ['permission:team.manage'])->where('id', '\d+');
    Router::post('/team/{id}/remove', [\App\Controllers\Tenant\TeamController::class, 'remove'], ['permission:team.manage'])->where('id', '\d+');

    // Settings
    Router::get('/settings', [\App\Controllers\Tenant\SettingsController::class, 'index'], ['permission:settings.view'])->name('settings');
    Router::post('/settings', [\App\Controllers\Tenant\SettingsController::class, 'update'], ['permission:settings.manage']);

    // Billing
    Router::get('/billing', [\App\Controllers\Tenant\BillingController::class, 'index'], ['permission:billing.view'])->name('billing');

    // Developers (API keys)
    Router::get('/developers', [\App\Controllers\Tenant\DeveloperController::class, 'index'], ['permission:api.keys'])->name('developers');
    Router::post('/developers/keys', [\App\Controllers\Tenant\DeveloperController::class, 'createKey'], ['permission:api.keys']);
    Router::post('/developers/keys/{id}/revoke', [\App\Controllers\Tenant\DeveloperController::class, 'revokeKey'], ['permission:api.keys'])->where('id', '\d+');

    // Flows
    Router::get('/flows', [\App\Controllers\Tenant\FlowController::class, 'index'], ['permission:flows.view'])->name('flows');
    Router::get('/flows/{id}/edit', [\App\Controllers\Tenant\FlowController::class, 'edit'], ['permission:flows.edit'])->where('id', '\d+');
    Router::post('/flows', [\App\Controllers\Tenant\FlowController::class, 'store'], ['permission:flows.create']);
    Router::post('/flows/{id}', [\App\Controllers\Tenant\FlowController::class, 'update'], ['permission:flows.edit'])->where('id', '\d+');
    Router::post('/flows/{id}/toggle', [\App\Controllers\Tenant\FlowController::class, 'toggle'], ['permission:flows.publish'])->where('id', '\d+');
    Router::post('/flows/{id}/delete', [\App\Controllers\Tenant\FlowController::class, 'destroy'], ['permission:flows.delete'])->where('id', '\d+');
});
