<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use Src\Ordering\Orders\Domain\Entities\Order;

interface RefundRequests
{
    /** Ask for the payment of a cancelled order to be given back to the customer. */
    public function request(Order $order): void;
}
