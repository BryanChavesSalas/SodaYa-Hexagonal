<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use Src\Ordering\Orders\Application\Contracts\LoyaltyRewards;
use Src\Ordering\Orders\Domain\Entities\Order;

final class FakeLoyaltyRewards implements LoyaltyRewards
{
    /** @var list<string> orders whose delivery was rewarded */
    public private(set) array $rewarded = [];

    /** Remember the delivered order. */
    public function orderDelivered(Order $order): void
    {
        $this->rewarded[] = $order->id->value;
    }
}
