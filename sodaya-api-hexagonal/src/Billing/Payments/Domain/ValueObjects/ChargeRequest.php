<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ChargeRequest
{
    /**
     * What a provider needs to charge once: the key that makes a retry harmless, the amount in colones,
     * the payment method of the customer and the order, for the records of the provider.
     */
    public function __construct(
        public string $idempotencyKey,
        public Amount $amount,
        public string $paymentMethod,
        public OrderId $orderId,
    ) {
        if (trim($idempotencyKey) === '') {
            throw new InvalidValueException('payments.idempotency_key_invalid');
        }

        if (trim($paymentMethod) === '') {
            throw new InvalidValueException('payments.payment_method_invalid');
        }
    }
}
