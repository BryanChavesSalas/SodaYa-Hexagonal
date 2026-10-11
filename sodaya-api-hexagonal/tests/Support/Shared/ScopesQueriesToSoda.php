<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Infrastructure\Tenancy\SodaTransaction;

trait ScopesQueriesToSoda
{
    /** Make the next queries of the test reach only the rows of a soda, until something scopes them again. */
    protected function scopeQueriesTo(?string $sodaId): void
    {
        $this->app->make(SodaTransaction::class)->scope($sodaId);
    }
}
