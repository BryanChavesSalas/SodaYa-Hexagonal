<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sodas\Profile\Infrastructure\Http\Controllers\SodaLogoController;

Route::prefix('cocina/logo')
    ->middleware(['auth:sanctum', 'soda:user', 'abilities:administrar'])
    ->name('sodas.profile.logo.')
    ->controller(SodaLogoController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
    });
