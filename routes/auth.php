<?php

declare(strict_types=1);

/**
 * Authentication routes.
 */

use App\Core\Router;

Router::group(['middleware' => ['guest']], function () {
    Router::get('/login', [\App\Controllers\Auth\LoginController::class, 'show'])->name('login');
    Router::post('/login', [\App\Controllers\Auth\LoginController::class, 'login'], ['csrf', 'throttle:10,1']);

    Router::get('/register', [\App\Controllers\Auth\RegisterController::class, 'show'])->name('register');
    Router::post('/register', [\App\Controllers\Auth\RegisterController::class, 'register'], ['csrf', 'throttle:5,1']);

    Router::get('/forgot-password', [\App\Controllers\Auth\ForgotPasswordController::class, 'show'])->name('password.request');
    Router::post('/forgot-password', [\App\Controllers\Auth\ForgotPasswordController::class, 'send'], ['csrf', 'throttle:5,5']);
    Router::get('/reset-password/{token}', [\App\Controllers\Auth\ForgotPasswordController::class, 'showReset'])->name('password.reset');
    Router::post('/reset-password', [\App\Controllers\Auth\ForgotPasswordController::class, 'reset'], ['csrf', 'throttle:5,5']);
});

// 2FA challenge (pending session, not fully authed yet)
Router::get('/2fa', [\App\Controllers\Auth\TwoFactorController::class, 'show'])->name('2fa');
Router::post('/2fa', [\App\Controllers\Auth\TwoFactorController::class, 'verify'], ['csrf', 'throttle:10,5']);

Router::post('/logout', [\App\Controllers\Auth\LoginController::class, 'logout'], ['auth', 'csrf'])->name('logout');

// 2FA management (settings)
Router::post('/profile/2fa/enable', [\App\Controllers\Auth\TwoFactorController::class, 'enable'], ['auth', 'csrf']);
Router::post('/profile/2fa/confirm', [\App\Controllers\Auth\TwoFactorController::class, 'confirm'], ['auth', 'csrf']);
Router::post('/profile/2fa/disable', [\App\Controllers\Auth\TwoFactorController::class, 'disable'], ['auth', 'csrf']);

// Impersonation exit (banner link)
Router::post('/impersonation/stop', [\App\Controllers\Auth\LoginController::class, 'stopImpersonating'], ['auth', 'csrf'])->name('impersonation.stop');
