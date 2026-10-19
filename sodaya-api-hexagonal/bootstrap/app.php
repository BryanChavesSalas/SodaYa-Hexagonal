<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Src\Shared\Infrastructure\Http\Middleware\AssignRequestId;
use Src\Shared\Infrastructure\Http\Middleware\EncodeJsonAsUnicode;
use Src\Shared\Infrastructure\Http\Problem\ProblemDetailsRenderer;
use Src\Shared\Infrastructure\Tenancy\ScopeRequestToSoda;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(EncodeJsonAsUnicode::class);
        $middleware->redirectGuestsTo(null);
        $middleware->trimStrings(except: ['contrasena']);

        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'soda' => ScopeRequestToSoda::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(new ProblemDetailsRenderer);
    })->create();
