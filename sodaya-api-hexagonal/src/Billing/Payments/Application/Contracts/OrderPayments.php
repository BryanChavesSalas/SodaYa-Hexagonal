<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\Contracts;

use DateTimeImmutable;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\ValueObjects\SodaId;

interface OrderPayments
{
    /** Tell the order that its payment was approved at that moment. */
    public function markPaid(OrderId $orderId, SodaId $sodaId, DateTimeImmutable $at): void;
}
