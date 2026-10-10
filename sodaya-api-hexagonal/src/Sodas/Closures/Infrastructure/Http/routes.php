<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sodas\Closures\Infrastructure\Http\Controllers\ClosureController;

Route::prefix('cocina/cierres')
    ->middleware('auth:sanctum')
    ->name('sodas.closures.')
    ->controller(ClosureController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('hoy', 'closeForToday')->name('close-for-today');
        Route::delete('{cierre}', 'destroy')->name('destroy')->whereUuid('cierre');
    });
