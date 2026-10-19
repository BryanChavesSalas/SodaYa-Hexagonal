<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Loyalty\Points\Infrastructure\Http\Controllers\PointsController;

Route::prefix('puntos')
    ->middleware(['auth:sanctum', 'abilities:pedidos'])
    ->name('loyalty.points.')
    ->controller(PointsController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::post('canjes', 'redeem')->name('redemptions.store');
    });
