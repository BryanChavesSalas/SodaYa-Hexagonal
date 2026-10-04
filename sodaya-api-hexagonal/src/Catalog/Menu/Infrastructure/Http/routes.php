<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Menu\Infrastructure\Http\Controllers\PublicMenuController;

Route::prefix('sodas/{soda}/platos')
    ->name('catalog.menu.')
    ->controller(PublicMenuController::class)
    ->whereUuid(['soda', 'plato'])
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('{plato}', 'show')->name('show');
    });
