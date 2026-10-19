<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Promotions\Infrastructure\Http\Controllers\PromotionController;

// The happy hours change the prices of the whole soda: only the owner sees and manages them.
Route::prefix('cocina/promociones')
    ->middleware(['auth:sanctum', 'soda:user', 'abilities:administrar'])
    ->name('catalog.promotions.')
    ->controller(PromotionController::class)
    ->whereUuid('promocion')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('{promocion}', 'destroy')->name('destroy');
    });
