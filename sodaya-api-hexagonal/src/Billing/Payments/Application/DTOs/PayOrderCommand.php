<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\DTOs;

final readonly class PayOrderCommand
{
    /** Carry the order to pay, the customer who pays and the payment method they chose; the amount is never theirs to send. */
    public function __construct(
        public string $orderId,
        public string $customerId,
        public string $paymentMethod,
    ) {}
}
