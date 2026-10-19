<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;

final readonly class ListCustomerOrders
{
    /** Receive the order repository port. */
    public function __construct(private OrderRepository $orders) {}

    /**
     * List one page of the orders of the customer, at every soda, newest first.
     *
     * @return Page<Order>
     */
    public function execute(string $customerId, PageRequest $page): Page
    {
        return $this->orders->pageOfCustomer(new CustomerId($customerId), $page);
    }
}
