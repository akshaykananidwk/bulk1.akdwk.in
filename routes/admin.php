<?php

declare(strict_types=1);

/**
 * Super-admin panel routes.
 */

use App\Core\Router;

Router::group(['prefix' => '/admin', 'middleware' => ['auth', 'admin', 'csrf'], 'name' => 'admin.'], function () {
    Router::get('/', [\App\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');
    Router::get('/dashboard', [\App\Controllers\Admin\DashboardController::class, 'index']);

    // Tenants
    Router::get('/tenants', [\App\Controllers\Admin\TenantController::class, 'index'])->name('tenants');
    Router::get('/tenants/{id}', [\App\Controllers\Admin\TenantController::class, 'show'])->where('id', '\d+')->name('tenants.show');
    Router::post('/tenants/{id}/suspend', [\App\Controllers\Admin\TenantController::class, 'suspend'])->where('id', '\d+');
    Router::post('/tenants/{id}/activate', [\App\Controllers\Admin\TenantController::class, 'activate'])->where('id', '\d+');
    Router::post('/tenants/{id}/extend-trial', [\App\Controllers\Admin\TenantController::class, 'extendTrial'])->where('id', '\d+');
    Router::post('/tenants/{id}/wallet', [\App\Controllers\Admin\TenantController::class, 'adjustWallet'])->where('id', '\d+');
    Router::post('/tenants/{id}/plan', [\App\Controllers\Admin\TenantController::class, 'changePlan'])->where('id', '\d+');
    Router::post('/tenants/{id}/impersonate', [\App\Controllers\Admin\TenantController::class, 'impersonate'])->where('id', '\d+');

    // Meta app config
    Router::get('/meta-app', [\App\Controllers\Admin\MetaAppController::class, 'index'])->name('meta_app');
    Router::post('/meta-app', [\App\Controllers\Admin\MetaAppController::class, 'save']);

    // Global settings
    Router::get('/settings', [\App\Controllers\Admin\SettingsController::class, 'index'])->name('settings');
    Router::post('/settings', [\App\Controllers\Admin\SettingsController::class, 'update']);

    // Plans
    Router::get('/plans', [\App\Controllers\Admin\PlanController::class, 'index'])->name('plans');
    Router::post('/plans', [\App\Controllers\Admin\PlanController::class, 'store']);
    Router::post('/plans/{id}', [\App\Controllers\Admin\PlanController::class, 'update'])->where('id', '\d+');

    // Transactions
    Router::get('/transactions', [\App\Controllers\Admin\TransactionController::class, 'index'])->name('transactions');

    // Pricing rates
    Router::get('/pricing-rates', [\App\Controllers\Admin\PricingRateController::class, 'index'])->name('pricing_rates');
    Router::post('/pricing-rates', [\App\Controllers\Admin\PricingRateController::class, 'store']);
    Router::post('/pricing-rates/{id}/delete', [\App\Controllers\Admin\PricingRateController::class, 'destroy'])->where('id', '\d+');

    // Queue monitor
    Router::get('/queue', [\App\Controllers\Admin\QueueController::class, 'index'])->name('queue');
    Router::post('/queue/retry', [\App\Controllers\Admin\QueueController::class, 'retryFailed']);
    Router::post('/queue/retry/{id}', [\App\Controllers\Admin\QueueController::class, 'retryOne'])->where('id', '\d+');
    Router::post('/queue/clear-failed', [\App\Controllers\Admin\QueueController::class, 'clearFailed']);

    // Logs
    Router::get('/logs', [\App\Controllers\Admin\LogController::class, 'index'])->name('logs');

    // System health
    Router::get('/health', [\App\Controllers\Admin\HealthController::class, 'index'])->name('health');

    // Backups
    Router::get('/backups', [\App\Controllers\Admin\BackupController::class, 'index'])->name('backups');
    Router::post('/backups/database', [\App\Controllers\Admin\BackupController::class, 'createDatabase'], ['permission:backup.manage']);
    Router::get('/backups/{id}/download', [\App\Controllers\Admin\BackupController::class, 'download'], ['permission:backup.manage'])->where('id', '\d+');
    Router::post('/backups/{id}/delete', [\App\Controllers\Admin\BackupController::class, 'destroy'], ['permission:backup.manage'])->where('id', '\d+');

    // Updates (GitHub auto-updater)
    Router::get('/updates', [\App\Controllers\Admin\UpdateController::class, 'index'], ['permission:update.manage'])->name('updates');
    Router::post('/updates/settings', [\App\Controllers\Admin\UpdateController::class, 'saveSettings'], ['permission:update.manage']);
    Router::post('/updates/test-connection', [\App\Controllers\Admin\UpdateController::class, 'testConnection'], ['permission:update.manage']);
    Router::post('/updates/check', [\App\Controllers\Admin\UpdateController::class, 'check'], ['permission:update.manage']);
    Router::post('/updates/run', [\App\Controllers\Admin\UpdateController::class, 'run'], ['permission:update.manage']);
    Router::get('/updates/stream', [\App\Controllers\Admin\UpdateController::class, 'stream'], ['permission:update.manage']);
    Router::post('/updates/rollback/{id}', [\App\Controllers\Admin\UpdateController::class, 'rollback'], ['permission:update.manage'])->where('id', '\d+');
    Router::get('/updates/verify', [\App\Controllers\Admin\UpdateController::class, 'verifyIntegrity'], ['permission:update.manage']);
    Router::get('/updates/diagnostics', [\App\Controllers\Admin\UpdateController::class, 'diagnostics'], ['permission:update.manage']);
});
