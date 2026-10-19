<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Application\UseCases;

use DateTimeImmutable;
use Src\Loyalty\Points\Application\DTOs\RedeemPointsCommand;
use Src\Loyalty\Points\Application\DTOs\Redemption;
use Src\Loyalty\Points\Domain\Contracts\PointMovementRepository;
use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\Exceptions\InsufficientPointsException;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;
use Src\Loyalty\Points\Domain\ValueObjects\Points;
use Src\Shared\Domain\Contracts\TransactionRunner;

final readonly class RedeemPoints
{
    /** Receive the ledger of points and the transaction port. */
    public function __construct(
        private PointMovementRepository $movements,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Spend points of the customer if the balance covers them. The ledger stays locked from the moment
     * the balance is read until the redemption is written, so two redemptions never read the same balance.
     */
    public function execute(RedeemPointsCommand $command, DateTimeImmutable $now): Redemption
    {
        return $this->transaction->run(function () use ($command, $now): Redemption {
            $customerId = new CustomerId($command->customerId);
            $points = new Points($command->points);

            $this->movements->lockLedgerOf($customerId);
            $balance = $this->movements->balanceOf($customerId);

            if ($balance < $points->value) {
                throw InsufficientPointsException::create($balance);
            }

            $movement = PointMovement::redemption($this->movements->nextId(), $customerId, $points, $now);
            $this->movements->add($movement);

            return new Redemption($movement, $balance - $points->value);
        });
    }
}
