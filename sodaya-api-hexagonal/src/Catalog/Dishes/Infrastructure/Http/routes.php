<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Dishes\Infrastructure\Http\Controllers\DishController;
use Src\Identity\Domain\Enums\Ability;
use Src\Identity\Infrastructure\Http\Middleware\RequireAbility;

Route::prefix('cocina/platos')
    ->name('catalog.dishes.')
    ->middleware(['auth:sanctum', RequireAbility::using(Ability::OperateKitchen)])
    ->controller(DishController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{plato}', 'update')->name('update')->whereUuid('plato');
    });
