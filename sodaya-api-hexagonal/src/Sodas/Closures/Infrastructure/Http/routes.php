<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sodas\Closures\Infrastructure\Http\Controllers\ClosureController;

Route::prefix('cocina/cierres')->group(function () {
    Route::get('/', [ClosureController::class, 'index']);
    Route::post('/', [ClosureController::class, 'store']);
    Route::post('/hoy', [ClosureController::class, 'closeToday']);
    Route::delete('/{cierre}', [ClosureController::class, 'destroy']);
});
