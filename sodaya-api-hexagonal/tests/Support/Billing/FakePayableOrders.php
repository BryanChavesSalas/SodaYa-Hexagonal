<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Src\Billing\Payments\Application\Contracts\PayableOrders;
use Src\Billing\Payments\Application\DTOs\PayableOrder;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;

final class FakePayableOrders implements PayableOrders
{
    /** @param  list<PayableOrder>  $orders  the orders that exist */
    public function __construct(private array $orders = []) {}

    /** Find the order if it exists and belongs to the customer. */
    public function find(OrderId $orderId, CustomerId $customerId): ?PayableOrder
    {
        foreach ($this->orders as $order) {
            if ($order->id === $orderId->value && $order->customerId === $customerId->value) {
                return $order;
            }
        }

        return null;
    }
}
