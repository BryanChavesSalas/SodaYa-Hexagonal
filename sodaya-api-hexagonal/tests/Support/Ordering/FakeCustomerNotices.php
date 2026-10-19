<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use Src\Ordering\Orders\Application\Contracts\CustomerNotices;
use Src\Ordering\Orders\Domain\Entities\Order;

final class FakeCustomerNotices implements CustomerNotices
{
    /** @var list<string> orders whose cancellation was notified */
    public private(set) array $cancelled = [];

    /** Remember the order whose cancellation was notified. */
    public function orderCancelled(Order $order): void
    {
        $this->cancelled[] = $order->id->value;
    }
}
