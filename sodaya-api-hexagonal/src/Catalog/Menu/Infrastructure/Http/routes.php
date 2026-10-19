<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Menu\Infrastructure\Http\Controllers\PublicMenuController;

// Browsers and proxies may reuse the menu for a minute and revalidate it with its ETag afterwards.
Route::prefix('sodas/{soda}/platos')
    ->middleware(['soda:route', 'cache.headers:public;max_age=60;etag'])
    ->name('catalog.menu.')
    ->controller(PublicMenuController::class)
    ->whereUuid(['soda', 'plato'])
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('{plato}', 'show')->name('show');
    });
