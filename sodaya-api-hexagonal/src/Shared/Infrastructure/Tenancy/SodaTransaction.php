<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Closure;
use Illuminate\Support\Facades\DB;

final readonly class SodaTransaction
{
    public const string SETTING = 'app.soda_id';

    public const string CUSTOMER_SETTING = 'app.customer_id';

    /**
     * Run the operation in a database transaction that only reaches the rows of one soda; null reaches none.
     * With a customer, the transaction also reads the orders of that customer, whatever their soda.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    public function run(?string $sodaId, Closure $operation, ?string $customerId = null): mixed
    {
        return DB::transaction(function () use ($sodaId, $customerId, $operation): mixed {
            $this->scope($sodaId);

            if ($customerId !== null) {
                $this->scopeCustomer($customerId);
            }

            return $operation();
        });
    }

    /** Set the soda of the open transaction; the value disappears when the transaction ends. */
    public function scope(?string $sodaId): void
    {
        DB::statement('select set_config(?, ?, true)', [self::SETTING, $sodaId ?? '']);
    }

    /** Set the customer of the open transaction, whose own orders become readable; it disappears with the transaction too. */
    public function scopeCustomer(?string $customerId): void
    {
        DB::statement('select set_config(?, ?, true)', [self::CUSTOMER_SETTING, $customerId ?? '']);
    }
}
