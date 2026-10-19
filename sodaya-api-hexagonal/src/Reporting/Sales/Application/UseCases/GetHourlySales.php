<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\UseCases;

use Src\Reporting\Sales\Application\Contracts\SalesReport;
use Src\Reporting\Sales\Application\DTOs\HourlySalesReport;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class GetHourlySales
{
    /** Receive the read port of the sales. */
    public function __construct(private SalesReport $report) {}

    /** Return the sales by hour of a soda on a date, keeping only the hours with the minimum of orders. */
    public function execute(string $sodaId, string $date, int $minimumOrders): HourlySalesReport
    {
        return new HourlySalesReport(
            $date,
            $minimumOrders,
            $this->report->hourlySales(new SodaId($sodaId), $date, $minimumOrders),
        );
    }
}
