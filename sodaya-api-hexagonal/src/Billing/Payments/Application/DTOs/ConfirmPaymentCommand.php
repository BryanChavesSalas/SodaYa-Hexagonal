<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\DTOs;

final readonly class ConfirmPaymentCommand
{
    /** Carry the order whose payment is asked about and the customer who asks; the state is never theirs to tell. */
    public function __construct(
        public string $orderId,
        public string $customerId,
    ) {}
}
