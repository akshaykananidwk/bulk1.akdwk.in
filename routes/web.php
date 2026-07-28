<?php

declare(strict_types=1);

/**
 * General web routes: home, session utilities, real-time endpoints.
 */

use App\Core\Auth;
use App\Core\Router;

Router::get('/', function () {
    if (Auth::check()) {
        \App\Core\Response::redirect(Auth::isSuperAdmin() ? '/admin' : '/tenant');
    }
    \App\Core\Response::redirect('/login');
})->name('home');

// Real-time
Router::get('/sse/stream', [\App\Controllers\Tenant\SseController::class, 'stream'], ['auth'])->name('sse.stream');
Router::get('/sse/poll', [\App\Controllers\Tenant\SseController::class, 'poll'], ['auth'])->name('sse.poll');
Router::post('/presence/ping', [\App\Controllers\Tenant\SseController::class, 'presencePing'], ['auth']);

// Profile utilities
Router::post('/profile/dark-mode', [\App\Controllers\Auth\ProfileController::class, 'darkMode'], ['auth']);
Router::get('/profile', [\App\Controllers\Auth\ProfileController::class, 'show'], ['auth', 'csrf'])->name('profile');
Router::post('/profile', [\App\Controllers\Auth\ProfileController::class, 'update'], ['auth', 'csrf']);
Router::post('/profile/password', [\App\Controllers\Auth\ProfileController::class, 'changePassword'], ['auth', 'csrf']);
Router::post('/profile/locale', [\App\Controllers\Auth\ProfileController::class, 'setLocale'], ['auth', 'csrf']);

// Uploaded media (auth-gated, tenant-scoped)
Router::get('/media/{path}', [\App\Controllers\Tenant\MediaController::class, 'serve'], ['auth'])
    ->where('path', '.+')->name('media.serve');
