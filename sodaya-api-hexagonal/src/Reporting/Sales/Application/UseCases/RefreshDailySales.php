<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\UseCases;

use DateTimeImmutable;
use Src\Reporting\Sales\Application\Contracts\DailySalesSummary;

final readonly class RefreshDailySales
{
    /** Receive the port that keeps the summary of every soda. */
    public function __construct(private DailySalesSummary $summary) {}

    /** Bring the daily summary of every soda up to date, as of the given moment. */
    public function execute(DateTimeImmutable $now): void
    {
        $this->summary->refresh($now);
    }
}
