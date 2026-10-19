<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Application\DTOs;

final readonly class AwardPurchasePointsCommand
{
    /** Carry the customer, the delivered order and its amount in colones, without the tip. */
    public function __construct(
        public string $customerId,
        public string $orderId,
        public int $amount,
    ) {}
}
