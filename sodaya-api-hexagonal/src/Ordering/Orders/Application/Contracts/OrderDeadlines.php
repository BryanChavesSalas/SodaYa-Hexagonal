<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use DateTimeImmutable;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\ValueObjects\SodaId;

interface OrderDeadlines
{
    /** Check at the given moment whether the time of the order ran out. */
    public function checkAt(OrderId $orderId, SodaId $sodaId, DateTimeImmutable $moment): void;
}
