<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LaravelPlus\Subscribe\Http\Controllers\Admin\DashboardController;
use LaravelPlus\Subscribe\Http\Controllers\Admin\ListController;
use LaravelPlus\Subscribe\Http\Controllers\Admin\SubscriberController;

Route::prefix(config('subscribe.admin.prefix', 'admin/subscribers'))
    ->middleware(config('subscribe.admin.middleware', ['web', 'auth', 'role:super-admin,admin']))
    ->name('admin.subscribers.')
    ->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('/stats', [DashboardController::class, 'stats'])->name('stats');

        Route::prefix('subscribers')->name('subscribers.')->group(function (): void {
            Route::get('/', [SubscriberController::class, 'index'])->name('index');
            Route::get('/export', [SubscriberController::class, 'export'])->name('export');
            Route::get('/{subscriber}', [SubscriberController::class, 'show'])->name('show');
            Route::put('/{subscriber}', [SubscriberController::class, 'update'])->name('update');
            Route::delete('/{subscriber}', [SubscriberController::class, 'destroy'])->name('destroy');
            Route::post('/{subscriber}/confirm', [SubscriberController::class, 'confirm'])->name('confirm');
            Route::post('/{subscriber}/resend', [SubscriberController::class, 'resendConfirmation'])->name('resend');
        });

        Route::prefix('lists')->name('lists.')->group(function (): void {
            Route::get('/', [ListController::class, 'index'])->name('index');
            Route::post('/', [ListController::class, 'store'])->name('store');
            Route::get('/{list}', [ListController::class, 'show'])->name('show');
            Route::put('/{list}', [ListController::class, 'update'])->name('update');
            Route::delete('/{list}', [ListController::class, 'destroy'])->name('destroy');
        });
    });
