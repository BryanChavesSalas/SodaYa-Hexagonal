<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\DTOs;

final readonly class AdvanceOrderCommand
{
    /** Carry the order, its soda and the status it should move to: confirmed, ready or delivered. */
    public function __construct(
        public string $orderId,
        public string $sodaId,
        public string $status,
    ) {}
}
