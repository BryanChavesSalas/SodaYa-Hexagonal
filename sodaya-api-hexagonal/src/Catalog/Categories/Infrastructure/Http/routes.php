<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Categories\Infrastructure\Http\Controllers\CategoryController;

Route::prefix('cocina/categorias')
    ->middleware('auth:sanctum')
    ->name('catalog.categories.')
    ->controller(CategoryController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('{categoria}', 'update')->name('update')->whereUuid('categoria');
        Route::delete('{categoria}', 'destroy')->name('destroy')->whereUuid('categoria');
    });
