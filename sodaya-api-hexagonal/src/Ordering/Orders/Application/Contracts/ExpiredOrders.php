<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\DTOs\OrderReference;

interface ExpiredOrders
{
    /**
     * Find, across every soda, the pending orders whose payment or confirmation deadline already passed, oldest first.
     *
     * @return list<OrderReference>
     */
    public function dueAt(DateTimeImmutable $now, int $limit): array;
}
