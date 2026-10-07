<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Domain\Entities;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Schedules\Domain\Exceptions\InvalidTimeRangeException;
use Src\Sodas\Schedules\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\Schedules\Domain\ValueObjects\ScheduleId;
use Src\Sodas\Schedules\Domain\ValueObjects\TimeOfDay;

/** A slot of a soda's day: opening included, closing excluded. */
final readonly class Schedule
{
    /** Register a slot whose opening is strictly before its closing. */
    public function __construct(
        public ScheduleId $id,
        public SodaId $sodaId,
        public DayOfWeek $day,
        public TimeOfDay $opensAt,
        public TimeOfDay $closesAt,
    ) {
        if (! $opensAt->isBefore($closesAt)) {
            throw InvalidTimeRangeException::create();
        }
    }

    /** Tell whether both slots share any instant of the same day of the same soda. */
    public function overlaps(self $other): bool
    {
        return $this->sodaId->equals($other->sodaId)
            && $this->day->equals($other->day)
            && $this->opensAt->isBefore($other->closesAt)
            && $other->opensAt->isBefore($this->closesAt);
    }
}
