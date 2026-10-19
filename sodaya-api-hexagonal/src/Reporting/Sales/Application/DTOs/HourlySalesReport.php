<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\DTOs;

final readonly class HourlySalesReport
{
    /**
     * Gather the hours of a date that reached the minimum of orders.
     *
     * @param  list<HourlySales>  $hours
     */
    public function __construct(
        public string $date,
        public int $minimumOrders,
        public array $hours,
    ) {}
}
