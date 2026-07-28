<?php

declare(strict_types=1);

/**
 * Public routes: QR redirects, form renderer, landing pages.
 */

use App\Core\Router;

// Marketing + legal pages
Router::get('/pricing', [\App\Controllers\PublicSite\SiteController::class, 'pricing'])->name('pricing');
Router::get('/privacy-policy', [\App\Controllers\PublicSite\SiteController::class, 'privacy'])->name('privacy');
Router::get('/terms', [\App\Controllers\PublicSite\SiteController::class, 'terms'])->name('terms');
Router::get('/data-deletion', [\App\Controllers\PublicSite\SiteController::class, 'dataDeletion'])->name('data_deletion');
Router::get('/refund-policy', [\App\Controllers\PublicSite\SiteController::class, 'refund'])->name('refund');
Router::get('/contact', [\App\Controllers\PublicSite\SiteController::class, 'contact'])->name('contact');
Router::post('/contact', [\App\Controllers\PublicSite\SiteController::class, 'contactSubmit'], ['csrf', 'throttle:5,5']);

Router::get('/q/{slug}', [\App\Controllers\PublicSite\QrRedirectController::class, 'handle'])
    ->where('slug', '[A-Za-z0-9]+')->name('qr.redirect');

Router::get('/f/{tenant}/{slug}', [\App\Controllers\PublicSite\FormRendererController::class, 'show'])
    ->where('tenant', '[a-z0-9-]+')->where('slug', '[a-z0-9-]+')->name('form.show');
Router::post('/f/{tenant}/{slug}', [\App\Controllers\PublicSite\FormRendererController::class, 'submit'], ['throttle:20,1'])
    ->where('tenant', '[a-z0-9-]+')->where('slug', '[a-z0-9-]+');

Router::get('/p/{tenant}/{slug}', [\App\Controllers\PublicSite\LandingPageController::class, 'show'])
    ->where('tenant', '[a-z0-9-]+')->where('slug', '[a-z0-9-]+')->name('landing.show');
