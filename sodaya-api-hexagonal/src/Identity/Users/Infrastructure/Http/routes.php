<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Identity\Users\Infrastructure\Http\Controllers\TokenController;

Route::prefix('tokens')
    ->name('identity.tokens.')
    ->controller(TokenController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
    });
