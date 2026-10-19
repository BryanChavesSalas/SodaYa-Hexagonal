<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\UseCases;

use Src\Reporting\Sales\Application\Contracts\SalesReport;
use Src\Reporting\Sales\Application\DTOs\DailySalesReport;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class GetDailySales
{
    /** Receive the read port of the sales. */
    public function __construct(private SalesReport $report) {}

    /** Return the sales by day of a soda between two dates, with the moment the summary was last computed. */
    public function execute(string $sodaId, string $from, string $to): DailySalesReport
    {
        return new DailySalesReport(
            $from,
            $to,
            $this->report->dailySales(new SodaId($sodaId), $from, $to),
            $this->report->dailySalesRefreshedAt(),
        );
    }
}
