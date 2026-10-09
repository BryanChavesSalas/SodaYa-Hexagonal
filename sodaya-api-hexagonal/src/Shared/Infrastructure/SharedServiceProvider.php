<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Infrastructure\OpenApi\ProblemResponsesTransformer;
use Src\Shared\Infrastructure\OpenApi\RelativeServerTransformer;
use Src\Shared\Infrastructure\Tenancy\AuthenticatedSodaContext;

final class SharedServiceProvider extends ServiceProvider
{
    /** Bind the shared ports to their adapters. */
    public function register(): void
    {
        $this->app->bind(SodaContext::class, AuthenticatedSodaContext::class);
    }

    /** Adjust the generated API contract. */
    public function boot(): void
    {
        Scramble::configure()->withDocumentTransformers([
            ProblemResponsesTransformer::class,
            RelativeServerTransformer::class,
        ]);
    }
}
