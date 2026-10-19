<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Application\UseCases;

use DateTimeImmutable;
use Src\Loyalty\Points\Application\DTOs\AwardPurchasePointsCommand;
use Src\Loyalty\Points\Domain\Contracts\PointMovementRepository;
use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\OrderId;
use Src\Loyalty\Points\Domain\ValueObjects\Points;

final readonly class AwardPurchasePoints
{
    /** Receive the ledger of points. */
    public function __construct(private PointMovementRepository $movements) {}

    /** Credit the points of a delivered order once; answer whether points were added. */
    public function execute(AwardPurchasePointsCommand $command, DateTimeImmutable $now): bool
    {
        $points = Points::earnedWith($command->amount);

        if ($points === null) {
            return false;
        }

        return $this->movements->add(PointMovement::purchase(
            $this->movements->nextId(),
            new CustomerId($command->customerId),
            new OrderId($command->orderId),
            $points,
            $now,
        ));
    }
}
