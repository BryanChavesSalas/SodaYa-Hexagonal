<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Identity\Domain\Enums\Ability;
use Src\Identity\Infrastructure\Http\Middleware\RequireAbility;
use Src\Sodas\Schedules\Infrastructure\Http\Controllers\ScheduleController;

Route::prefix('horarios')
    ->name('sodas.schedules.')
    ->middleware(['auth:sanctum', RequireAbility::using(Ability::Administer)])
    ->controller(ScheduleController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
    });
