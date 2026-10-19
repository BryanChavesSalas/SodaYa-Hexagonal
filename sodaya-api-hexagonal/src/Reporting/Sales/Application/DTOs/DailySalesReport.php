<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\DTOs;

use DateTimeImmutable;

final readonly class DailySalesReport
{
    /**
     * Gather the days of a range that had delivered orders, and the moment the summary they come from was computed.
     *
     * @param  list<DailySales>  $days
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $days,
        public ?DateTimeImmutable $refreshedAt,
    ) {}
}
