<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\DTOs;

use Src\Ordering\Orders\Domain\Entities\Order;

final readonly class PlacedOrder
{
    /** Carry the order a request produced and whether this request created it or found it already placed. */
    public function __construct(
        public Order $order,
        public bool $created,
    ) {}
}
