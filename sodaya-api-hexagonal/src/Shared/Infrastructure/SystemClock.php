<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Src\Shared\Domain\Clock;

final readonly class SystemClock implements Clock
{
    /** Return the real current time (honors Carbon::setTestNow in tests). */
    public function now(): DateTimeImmutable
    {
        return Carbon::now()->toDateTimeImmutable();
    }
}
