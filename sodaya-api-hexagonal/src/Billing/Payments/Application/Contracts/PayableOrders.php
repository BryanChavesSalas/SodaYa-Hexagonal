<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Application\Contracts;

use Src\Billing\Payments\Application\DTOs\PayableOrder;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;

interface PayableOrders
{
    /** Find an order of the customer with what a payment needs to know about it; an order of someone else is not found. */
    public function find(OrderId $orderId, CustomerId $customerId): ?PayableOrder;
}
