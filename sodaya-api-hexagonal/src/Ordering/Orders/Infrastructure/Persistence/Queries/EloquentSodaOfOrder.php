<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Persistence\Queries;

use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\SodaOfOrder;

final readonly class EloquentSodaOfOrder implements SodaOfOrder
{
    /** Read the soda of the order the transaction can see; with only a customer set, that is one of their own orders. */
    public function resolve(string $orderId): string
    {
        $sodaId = OrderModel::query()->whereKey($orderId)->value('soda_id');

        return is_string($sodaId) ? $sodaId : throw OrderNotFoundException::create();
    }
}
