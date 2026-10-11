<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sodas\OpeningHours\Infrastructure\Http\Controllers\TimeSlotController;

Route::prefix('cocina/horario')
    ->middleware('auth:sanctum')
    ->name('sodas.opening-hours.')
    ->controller(TimeSlotController::class)
    ->whereUuid('franja')
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware('abilities:cocina')
            ->name('index');

        Route::middleware('abilities:administrar')->group(function (): void {
            Route::post('/', 'store')->name('store');
            Route::delete('{franja}', 'destroy')->name('destroy');
        });
    });
