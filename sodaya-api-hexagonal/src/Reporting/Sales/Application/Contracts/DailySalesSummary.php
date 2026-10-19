<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Application\Contracts;

use DateTimeImmutable;

interface DailySalesSummary
{
    /** Recompute the delivered orders and the sales of every soda and day, and record the moment it was done. */
    public function refresh(DateTimeImmutable $now): void;
}
