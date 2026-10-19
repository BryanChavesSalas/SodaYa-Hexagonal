<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\Contracts;

use DateTimeImmutable;
use Src\Reporting\Sales\Application\DTOs\DailySales;
use Src\Reporting\Sales\Application\DTOs\HourlySales;
use Src\Shared\Domain\ValueObjects\SodaId;

interface SalesReport
{
    /**
     * Read, hour by hour, the delivered orders of a soda created on a date and what they sold, skipping the hours below the minimum.
     *
     * @return list<HourlySales>
     */
    public function hourlySales(SodaId $sodaId, string $date, int $minimumOrders): array;

    /**
     * Read, day by day, the delivered orders of a soda created between two dates, both included, and what they sold.
     *
     * @return list<DailySales>
     */
    public function dailySales(SodaId $sodaId, string $from, string $to): array;

    /** Moment the daily summary was last brought up to date; null if it never was. */
    public function dailySalesRefreshedAt(): ?DateTimeImmutable;
}
