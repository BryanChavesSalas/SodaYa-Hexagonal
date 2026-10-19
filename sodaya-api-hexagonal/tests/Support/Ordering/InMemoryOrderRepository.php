<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;

final class InMemoryOrderRepository implements OrderRepository
{
    /** @var array<string, Order> */
    private array $orders = [];

    private int $sequence = 0;

    public int $saves = 0;

    /** Generate a predictable identity. */
    public function nextId(): OrderId
    {
        return new OrderId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store a copy of the order, as a database would; a key already used by the customer is refused like the unique index does. */
    public function save(Order $order): void
    {
        $key = $order->idempotencyKey;

        if ($key !== null) {
            $previous = $this->findByIdempotencyKey($order->customerId, $key->value);

            if ($previous !== null && ! $previous->id->equals($order->id)) {
                throw IdempotencyKeyReusedException::create();
            }
        }

        $this->saves++;
        $this->orders[$order->id->value] = clone $order;
    }

    /** Find a copy of an order of the soda. */
    public function find(OrderId $id, SodaId $sodaId): ?Order
    {
        $order = $this->orders[$id->value] ?? null;

        return $order !== null && $order->sodaId->equals($sodaId) ? clone $order : null;
    }

    /** Same as find(): a single process needs no lock. */
    public function findForUpdate(OrderId $id, SodaId $sodaId): ?Order
    {
        return $this->find($id, $sodaId);
    }

    /** Find a copy of the order the customer placed with the key. */
    public function findByIdempotencyKey(CustomerId $customerId, string $key): ?Order
    {
        foreach ($this->orders as $order) {
            if ($order->customerId->equals($customerId) && $order->idempotencyKey?->value === $key) {
                return clone $order;
            }
        }

        return null;
    }

    /** Find a copy of an order of the customer, whatever its soda. */
    public function findOfCustomer(OrderId $id, CustomerId $customerId): ?Order
    {
        $order = $this->orders[$id->value] ?? null;

        return $order !== null && $order->customerId->equals($customerId) ? clone $order : null;
    }

    /** Page the orders of the customer newest first, as the database does. */
    public function pageOfCustomer(CustomerId $customerId, PageRequest $page): Page
    {
        $orders = array_values(array_filter($this->orders, fn (Order $order): bool => $order->customerId->equals($customerId)));
        usort($orders, fn (Order $a, Order $b): int => [$b->placedAt, $b->id->value] <=> [$a->placedAt, $a->id->value]);

        return $this->pageOf($orders, $page);
    }

    /** Page the orders of the soda oldest first, in one status or in any, as the database does. */
    public function pageOfSoda(SodaId $sodaId, ?OrderStatus $status, PageRequest $page): Page
    {
        $orders = array_values(array_filter(
            $this->orders,
            fn (Order $order): bool => $order->sodaId->equals($sodaId) && ($status === null || $order->status === $status),
        ));
        usort($orders, fn (Order $a, Order $b): int => [$a->placedAt, $a->id->value] <=> [$b->placedAt, $b->id->value]);

        return $this->pageOf($orders, $page);
    }

    /**
     * Cut one page out of the sorted orders.
     *
     * @param  list<Order>  $orders
     * @return Page<Order>
     */
    private function pageOf(array $orders, PageRequest $page): Page
    {
        return new Page(
            array_map(fn (Order $order): Order => clone $order, array_slice($orders, $page->offset(), $page->size)),
            count($orders),
            $page,
        );
    }
}
