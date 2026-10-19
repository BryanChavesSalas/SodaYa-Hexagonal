<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Ordering\Orders\Infrastructure\Http\Controllers\CustomerOrderController;
use Src\Ordering\Orders\Infrastructure\Http\Controllers\KitchenOrderController;

Route::post('sodas/{soda}/pedidos', [CustomerOrderController::class, 'store'])
    ->middleware(['auth:sanctum', 'abilities:pedidos', 'soda:route'])
    ->whereUuid('soda')
    ->name('ordering.orders.store');

Route::prefix('cocina/pedidos')
    ->middleware(['auth:sanctum', 'soda:user', 'abilities:cocina'])
    ->name('ordering.kitchen-orders.')
    ->controller(KitchenOrderController::class)
    ->whereUuid('pedido')
    ->group(function (): void {
        Route::patch('{pedido}/estado', 'updateStatus')->name('status.update');
        Route::post('{pedido}/cancelacion', 'cancel')->name('cancellation.store');
    });
