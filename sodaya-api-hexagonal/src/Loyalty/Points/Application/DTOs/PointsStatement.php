<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Application\DTOs;

use Src\Loyalty\Points\Domain\Entities\PointMovement;

final readonly class PointsStatement
{
    /**
     * The balance of a customer and its latest movements, newest first.
     *
     * @param  list<PointMovement>  $movements
     */
    public function __construct(
        public int $balance,
        public array $movements,
    ) {}
}
