<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;

/**
 * For tests whose rows must be visible to another connection or process: no transaction wraps the test,
 * so every row is committed, and the tear down removes them all.
 */
trait CommitsRows
{
    use RefreshDatabaseAsOwner;

    /** @var list<string> */
    protected array $connectionsToTransact = [];

    /**
     * Create rows of a soda in their own committed transaction.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $create
     * @return TResult
     */
    protected function committedFor(string $sodaId, Closure $create): mixed
    {
        return app(SodaTransaction::class)->run($sodaId, $create);
    }

    /** Remove every committed row: truncating sodas and users cascades to every table that points to them. */
    protected function tearDown(): void
    {
        DB::connection('pgsql_admin')->statement('TRUNCATE sodas, users CASCADE');

        parent::tearDown();
    }
}
