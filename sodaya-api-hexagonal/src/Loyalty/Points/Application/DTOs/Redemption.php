<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Application\DTOs;

use Src\Loyalty\Points\Domain\Entities\PointMovement;

final readonly class Redemption
{
    /** The movement that spent the points and the balance it left. */
    public function __construct(
        public PointMovement $movement,
        public int $balance,
    ) {}
}
