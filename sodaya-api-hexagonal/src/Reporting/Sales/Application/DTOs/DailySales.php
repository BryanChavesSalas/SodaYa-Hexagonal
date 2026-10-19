<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\DTOs;

final readonly class DailySales
{
    /** Describe the delivered orders created on one day, in Costa Rica dates, and what they sold. */
    public function __construct(
        public string $date,
        public int $orders,
        public int $total,
    ) {}
}
