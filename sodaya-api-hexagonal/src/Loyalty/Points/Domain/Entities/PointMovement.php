<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Domain\Entities;

use DateTimeImmutable;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\MovementId;
use Src\Loyalty\Points\Domain\ValueObjects\MovementReason;
use Src\Loyalty\Points\Domain\ValueObjects\OrderId;
use Src\Loyalty\Points\Domain\ValueObjects\Points;

/** One line of the ledger of a customer: it is written once and never changes. */
final readonly class PointMovement
{
    /** Hold the movement; $points is positive when it adds and negative when it takes. */
    private function __construct(
        public MovementId $id,
        public CustomerId $customerId,
        public ?OrderId $orderId,
        public MovementReason $reason,
        public int $points,
        public DateTimeImmutable $createdAt,
    ) {}

    /** Points the customer earns with an order. */
    public static function purchase(MovementId $id, CustomerId $customerId, OrderId $orderId, Points $points, DateTimeImmutable $at): self
    {
        return new self($id, $customerId, $orderId, MovementReason::Purchase, $points->value, $at);
    }

    /** Points the customer spends. */
    public static function redemption(MovementId $id, CustomerId $customerId, Points $points, DateTimeImmutable $at): self
    {
        return new self($id, $customerId, null, MovementReason::Redemption, -$points->value, $at);
    }

    /** Rebuild a movement from its stored state. */
    public static function reconstitute(
        MovementId $id,
        CustomerId $customerId,
        ?OrderId $orderId,
        MovementReason $reason,
        int $points,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $customerId, $orderId, $reason, $points, $createdAt);
    }
}
