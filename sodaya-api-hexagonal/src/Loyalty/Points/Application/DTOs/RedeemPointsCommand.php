<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Application\DTOs;

final readonly class RedeemPointsCommand
{
    /** Carry who spends points and how many. */
    public function __construct(
        public string $customerId,
        public int $points,
    ) {}
}
