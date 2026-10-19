<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Reporting\Sales\Infrastructure\Http\Controllers\SalesReportController;

Route::prefix('cocina/reportes')
    ->middleware(['auth:sanctum', 'soda:user', 'abilities:administrar'])
    ->name('reporting.sales.')
    ->controller(SalesReportController::class)
    ->group(function (): void {
        Route::get('ventas-por-hora', 'hourly')->name('hourly');
    });
