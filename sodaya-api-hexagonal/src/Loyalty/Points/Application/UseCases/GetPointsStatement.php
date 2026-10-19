<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Application\UseCases;

use Src\Loyalty\Points\Application\DTOs\PointsStatement;
use Src\Loyalty\Points\Domain\Contracts\PointMovementRepository;
use Src\Loyalty\Points\Domain\ValueObjects\CustomerId;

final readonly class GetPointsStatement
{
    /** Movements a statement shows. */
    public const int LATEST_MOVEMENTS = 20;

    /** Receive the ledger of points. */
    public function __construct(private PointMovementRepository $movements) {}

    /** Return the balance of the customer and the movements it comes from, newest first. */
    public function execute(string $customerId): PointsStatement
    {
        $customer = new CustomerId($customerId);

        return new PointsStatement(
            $this->movements->balanceOf($customer),
            $this->movements->latestOf($customer, self::LATEST_MOVEMENTS),
        );
    }
}
