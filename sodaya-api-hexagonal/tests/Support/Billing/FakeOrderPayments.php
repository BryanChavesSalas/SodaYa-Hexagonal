<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use DateTimeImmutable;
use Src\Billing\Payments\Application\Contracts\OrderPayments;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\ValueObjects\SodaId;

final class FakeOrderPayments implements OrderPayments
{
    /** @var array<string, DateTimeImmutable> moment each order was marked paid, by order */
    public private(set) array $paid = [];

    /** Remember when the order was marked paid. */
    public function markPaid(OrderId $orderId, SodaId $sodaId, DateTimeImmutable $at): void
    {
        $this->paid[$orderId->value] = $at;
    }
}
