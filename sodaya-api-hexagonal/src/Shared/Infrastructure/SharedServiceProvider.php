<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Infrastructure\Tenancy\ConfiguredSodaContext;

final class SharedServiceProvider extends ServiceProvider
{
    /** Bind the shared ports to their adapters. */
    public function register(): void
    {
        $this->app->bind(
            SodaContext::class,
            fn (): SodaContext => new ConfiguredSodaContext(config('sodaya.default_soda_id')),
        );
    }
}
