<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;

final readonly class SodaTransaction
{
    public const string SETTING = 'app.soda_id';

    /**
     * Run the operation in a database transaction that only reaches the rows of one soda; null reaches none.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function run(?string $sodaId, Closure $operation): mixed
    {
        return DB::transaction(function () use ($sodaId, $operation): mixed {
            $this->scope($sodaId);

            return $operation();
        });
    }

    /** Set the soda of the open transaction; the value disappears when the transaction ends. */
    public function scope(?string $sodaId): void
    {
        DB::statement('select set_config(?, ?, true)', [self::SETTING, $sodaId ?? '']);
    }
}
