<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ListKitchenOrders
{
    /** Receive the order repository port. */
    public function __construct(private OrderRepository $orders) {}

    /**
     * List one page of the orders of the soda, in one status or in any, oldest first: the order they arrived is the order to prepare them.
     *
     * @return Page<Order>
     */
    public function execute(string $sodaId, ?string $status, PageRequest $page): Page
    {
        return $this->orders->pageOfSoda(new SodaId($sodaId), $status === null ? null : OrderStatus::from($status), $page);
    }
}
