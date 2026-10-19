<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use Src\Ordering\Orders\Domain\Entities\Order;

interface CustomerNotices
{
    /** Let the customer know that the order was cancelled and why. */
    public function orderCancelled(Order $order): void;
}
