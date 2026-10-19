<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\DTOs;

final readonly class HourlySales
{
    /** Describe the delivered orders created in one hour of the day, in Costa Rica time, and what they sold. */
    public function __construct(
        public int $hour,
        public int $orders,
        public int $total,
    ) {}
}
