<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\DTOs;

final readonly class OrderReference
{
    /** Point to an order and its soda. */
    public function __construct(
        public string $orderId,
        public string $sodaId,
    ) {}
}
