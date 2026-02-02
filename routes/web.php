<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Nejcc\Subscribe\Http\Controllers\SubscribeController;

Route::prefix(config('subscribe.routes.prefix', 'subscribe'))
    ->middleware(config('subscribe.routes.middleware', ['web']))
    ->name('subscribe.')
    ->group(function (): void {
        Route::post('/', [SubscribeController::class, 'subscribe'])->name('store');
        Route::get('/confirm/{token}', [SubscribeController::class, 'confirm'])->name('confirm');
        Route::get('/unsubscribe/{token}', [SubscribeController::class, 'unsubscribeForm'])->name('unsubscribe.form');
        Route::post('/unsubscribe', [SubscribeController::class, 'unsubscribe'])->name('unsubscribe');
    });
