<?php

declare(strict_types=1);

/**
 * Public routes: QR redirects, form renderer, landing pages.
 */

use App\Core\Router;

Router::get('/q/{slug}', [\App\Controllers\PublicSite\QrRedirectController::class, 'handle'])
    ->where('slug', '[A-Za-z0-9]+')->name('qr.redirect');

Router::get('/f/{tenant}/{slug}', [\App\Controllers\PublicSite\FormRendererController::class, 'show'])
    ->where('tenant', '[a-z0-9-]+')->where('slug', '[a-z0-9-]+')->name('form.show');
Router::post('/f/{tenant}/{slug}', [\App\Controllers\PublicSite\FormRendererController::class, 'submit'], ['throttle:20,1'])
    ->where('tenant', '[a-z0-9-]+')->where('slug', '[a-z0-9-]+');

Router::get('/p/{tenant}/{slug}', [\App\Controllers\PublicSite\LandingPageController::class, 'show'])
    ->where('tenant', '[a-z0-9-]+')->where('slug', '[a-z0-9-]+')->name('landing.show');
