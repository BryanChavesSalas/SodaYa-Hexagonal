<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Contracts;

use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;

interface OrderRepository
{
    /** Generate the identity for a new order. */
    public function nextId(): OrderId;

    /**
     * Persist a new order with its lines, or the status of an existing one.
     *
     * @throws IdempotencyKeyReusedException when the customer already placed an order with the same key
     */
    public function save(Order $order): void;

    /** Find an order of the soda with its lines. */
    public function find(OrderId $id, SodaId $sodaId): ?Order;

    /** Find an order of the soda and lock it until the transaction ends, so two changes never overlap. */
    public function findForUpdate(OrderId $id, SodaId $sodaId): ?Order;

    /** Find the order a customer placed with a key, if the key was used. */
    public function findByIdempotencyKey(CustomerId $customerId, string $key): ?Order;

    /** Find an order of the customer with its lines, whatever its soda. */
    public function findOfCustomer(OrderId $id, CustomerId $customerId): ?Order;

    /**
     * List one page of the orders of the customer, at every soda, newest first.
     *
     * @return Page<Order>
     */
    public function pageOfCustomer(CustomerId $customerId, PageRequest $page): Page;
}
