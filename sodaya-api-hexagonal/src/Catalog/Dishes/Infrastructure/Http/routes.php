<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Dishes\Infrastructure\Http\Controllers\DishController;

Route::prefix('cocina/platos')
    ->middleware('auth:sanctum')
    ->name('catalog.dishes.')
    ->controller(DishController::class)
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware('abilities:cocina')
            ->name('index');

        Route::middleware('abilities:administrar')->group(function (): void {
            Route::post('/', 'store')->name('store');

            Route::patch('{plato}', 'update')
                ->name('update')
                ->whereUuid('plato');
        });
    });
