<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\OrderDeadlines;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\ValueObjects\SodaId;

final class FakeOrderDeadlines implements OrderDeadlines
{
    /** @var array<string, DateTimeImmutable> moment of the check, by order */
    public private(set) array $checks = [];

    /** Remember when the order is checked. */
    public function checkAt(OrderId $orderId, SodaId $sodaId, DateTimeImmutable $moment): void
    {
        $this->checks[$orderId->value] = $moment;
    }
}
