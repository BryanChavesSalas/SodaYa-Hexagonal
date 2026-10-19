<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Billing\Payments\Infrastructure\Http\Controllers\OrderPaymentController;

Route::prefix('pedidos/{pedido}/pago')
    ->middleware(['auth:sanctum', 'abilities:pedidos', 'soda:order'])
    ->whereUuid('pedido')
    ->name('billing.payments.')
    ->controller(OrderPaymentController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::get('/', 'show')->name('show');
    });
