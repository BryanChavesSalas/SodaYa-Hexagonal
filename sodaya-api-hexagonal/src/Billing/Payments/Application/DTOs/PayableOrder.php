<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\DTOs;

final readonly class PayableOrder
{
    /** What a payment needs to know about an order: whose it is, what it is worth and whether it can still be paid. */
    public function __construct(
        public string $id,
        public string $sodaId,
        public string $customerId,
        public int $amount,
        public bool $pending,
        public bool $paid,
    ) {}
}
