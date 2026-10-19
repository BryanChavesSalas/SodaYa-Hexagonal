<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use Src\Ordering\Orders\Application\Contracts\CustomerNotices;
use Src\Ordering\Orders\Domain\Entities\Order;

final class FakeCustomerNotices implements CustomerNotices
{
    /** @var list<string> orders whose readiness was notified */
    public private(set) array $ready = [];

    /** @var list<string> orders whose cancellation was notified */
    public private(set) array $cancelled = [];

    /** Remember the order that was announced as ready. */
    public function orderReady(Order $order): void
    {
        $this->ready[] = $order->id->value;
    }

    /** Remember the order whose cancellation was notified. */
    public function orderCancelled(Order $order): void
    {
        $this->cancelled[] = $order->id->value;
    }
}
