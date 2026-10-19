<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\Contracts;

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
}
