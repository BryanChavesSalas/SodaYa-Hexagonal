<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Contracts;

use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\ValueObjects\SodaId;

interface OrderRepository
{
    /** Generate the identity for a new order. */
    public function nextId(): OrderId;

    /** Persist a new order with its lines, or the status of an existing one. */
    public function save(Order $order): void;

    /** Find an order of the soda with its lines. */
    public function find(OrderId $id, SodaId $sodaId): ?Order;

    /** Find an order of the soda and lock it until the transaction ends, so two changes never overlap. */
    public function findForUpdate(OrderId $id, SodaId $sodaId): ?Order;
}
