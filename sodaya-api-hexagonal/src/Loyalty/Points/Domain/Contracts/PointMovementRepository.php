<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Domain\Contracts;

use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\MovementId;

interface PointMovementRepository
{
    /** Generate the identity of a new movement. */
    public function nextId(): MovementId;

    /** Add a movement to the ledger; answer false when the order already earned its points and nothing was added. */
    public function add(PointMovement $movement): bool;

    /** Keep any other transaction from changing the ledger of the customer until the current one ends. */
    public function lockLedgerOf(CustomerId $customerId): void;

    /** Points of the customer: the sum of every movement. */
    public function balanceOf(CustomerId $customerId): int;

    /**
     * The latest movements of the customer, newest first.
     *
     * @return list<PointMovement>
     */
    public function latestOf(CustomerId $customerId, int $limit): array;
}
