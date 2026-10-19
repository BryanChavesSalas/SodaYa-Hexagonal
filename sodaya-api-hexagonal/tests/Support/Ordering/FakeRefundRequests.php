<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use Src\Ordering\Orders\Application\Contracts\RefundRequests;
use Src\Ordering\Orders\Domain\Entities\Order;

final class FakeRefundRequests implements RefundRequests
{
    /** @var list<string> orders whose refund was requested */
    public private(set) array $requested = [];

    /** Remember the order whose refund was requested. */
    public function request(Order $order): void
    {
        $this->requested[] = $order->id->value;
    }
}
