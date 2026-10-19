<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\DTOs;

final readonly class CancelOrderCommand
{
    /** Carry the order, its soda and who cancels it: customer, kitchen, payment_timeout or confirmation_timeout. */
    public function __construct(
        public string $orderId,
        public string $sodaId,
        public string $origin,
    ) {}
}
