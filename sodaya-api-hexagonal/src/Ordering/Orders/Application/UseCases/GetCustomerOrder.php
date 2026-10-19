<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;

final readonly class GetCustomerOrder
{
    /** Receive the order repository port. */
    public function __construct(private OrderRepository $orders) {}

    /** Read one order of the customer; an order of someone else is as missing as one that does not exist. */
    public function execute(string $orderId, string $customerId): Order
    {
        return $this->orders->findOfCustomer(new OrderId($orderId), new CustomerId($customerId))
            ?? throw OrderNotFoundException::create();
    }
}
