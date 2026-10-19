<?php

declare(strict_types=1);

namespace Tests\Support\Loyalty;

use Src\Loyalty\Points\Domain\Contracts\PointMovementRepository;
use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\MovementId;
use Src\Loyalty\Points\Domain\ValueObjects\MovementReason;

final class InMemoryPointMovementRepository implements PointMovementRepository
{
    /** @var list<PointMovement> */
    private array $movements = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): MovementId
    {
        return new MovementId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Add the movement unless its order already earned points, as the partial unique index does. */
    public function add(PointMovement $movement): bool
    {
        foreach ($this->movements as $stored) {
            $samePurchase = $stored->reason === MovementReason::Purchase
                && $movement->reason === MovementReason::Purchase
                && $stored->orderId?->value === $movement->orderId?->value;

            if ($samePurchase) {
                return false;
            }
        }

        $this->movements[] = $movement;

        return true;
    }

    /** @var list<string> customers whose ledger was locked */
    public private(set) array $locked = [];

    /** Remember the lock; one process needs no more. */
    public function lockLedgerOf(CustomerId $customerId): void
    {
        $this->locked[] = $customerId->value;
    }

    /** Add up the movements of the customer. */
    public function balanceOf(CustomerId $customerId): int
    {
        return array_sum(array_map(fn (PointMovement $movement): int => $movement->points, $this->of($customerId)));
    }

    /** The latest movements of the customer, newest first. */
    public function latestOf(CustomerId $customerId, int $limit): array
    {
        return array_slice(array_reverse($this->of($customerId)), 0, $limit);
    }

    /**
     * Movements of the customer in the order they were added.
     *
     * @return list<PointMovement>
     */
    private function of(CustomerId $customerId): array
    {
        return array_values(array_filter($this->movements, fn (PointMovement $movement): bool => $movement->customerId->equals($customerId)));
    }
}
