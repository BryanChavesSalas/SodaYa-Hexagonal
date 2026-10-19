<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Billing\Payments\Infrastructure\Http\Controllers\OrderPaymentController;

Route::post('pedidos/{pedido}/pago', [OrderPaymentController::class, 'store'])
    ->middleware(['auth:sanctum', 'abilities:pedidos', 'soda:order'])
    ->whereUuid('pedido')
    ->name('billing.payments.store');
