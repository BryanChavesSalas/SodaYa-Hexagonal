<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sodas\OpeningHours\Infrastructure\Http\Controllers\TimeSlotController;

Route::middleware('auth:sanctum')
    ->prefix('cocina/horario')
    ->name('sodas.opening-hours.')
    ->controller(TimeSlotController::class)
    ->whereUuid('franja')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('{franja}', 'destroy')->name('destroy');
    });
