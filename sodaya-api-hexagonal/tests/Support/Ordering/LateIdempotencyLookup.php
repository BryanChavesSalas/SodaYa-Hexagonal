<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\ValueObjects\SodaId;

/** A repository whose first lookup by key arrives too early to see the order another request is saving. */
final class LateIdempotencyLookup implements OrderRepository
{
    private bool $firstLookup = true;

    /** Wrap the repository that holds the orders of the scenario. */
    public function __construct(private readonly InMemoryOrderRepository $inner) {}

    /** Delegate. */
    public function nextId(): OrderId
    {
        return $this->inner->nextId();
    }

    /** Delegate: the inner repository refuses a key already used. */
    public function save(Order $order): void
    {
        $this->inner->save($order);
    }

    /** Delegate. */
    public function find(OrderId $id, SodaId $sodaId): ?Order
    {
        return $this->inner->find($id, $sodaId);
    }

    /** Delegate. */
    public function findForUpdate(OrderId $id, SodaId $sodaId): ?Order
    {
        return $this->inner->findForUpdate($id, $sodaId);
    }

    /** See nothing the first time, as if the other request had not committed yet; afterwards, delegate. */
    public function findByIdempotencyKey(CustomerId $customerId, string $key): ?Order
    {
        if ($this->firstLookup) {
            $this->firstLookup = false;

            return null;
        }

        return $this->inner->findByIdempotencyKey($customerId, $key);
    }
}
