<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use Src\Ordering\Orders\Domain\Entities\Order;

interface LoyaltyRewards
{
    /** Reward the customer for an order that was just delivered. */
    public function orderDelivered(Order $order): void;
}
