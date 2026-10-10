<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Domain\ValueObjects;

use DateTimeImmutable;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;

final readonly class WeeklySchedule
{
    /**
     * Gather the slots a soda opens on during the week.
     *
     * @param  list<TimeSlot>  $slots
     */
    public function __construct(private array $slots) {}

    /** Tell whether some slot covers the day and time of the moment, in its own time zone. */
    public function isOpenAt(DateTimeImmutable $moment): bool
    {
        $day = DayOfWeek::fromMoment($moment);
        $time = TimeOfDay::fromMoment($moment);

        return array_any($this->slots, fn (TimeSlot $slot): bool => $slot->includes($day, $time));
    }
}
