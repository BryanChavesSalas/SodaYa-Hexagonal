<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Domain\Entities;

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeOfDay;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeSlotId;

final readonly class TimeSlot
{
    /** Hold the full state of a time slot. */
    private function __construct(
        public TimeSlotId $id,
        public SodaId $sodaId,
        public DayOfWeek $day,
        public TimeOfDay $opensAt,
        public TimeOfDay $closesAt,
    ) {}

    /** Register a new slot whose opening comes before its closing. */
    public static function create(
        TimeSlotId $id,
        SodaId $sodaId,
        DayOfWeek $day,
        TimeOfDay $opensAt,
        TimeOfDay $closesAt,
    ): self {
        if (! $opensAt->isBefore($closesAt)) {
            throw new InvalidValueException('sodas.time_slot_inverted');
        }

        return new self($id, $sodaId, $day, $opensAt, $closesAt);
    }

    /** Rebuild a slot from its stored state. */
    public static function reconstitute(
        TimeSlotId $id,
        SodaId $sodaId,
        DayOfWeek $day,
        TimeOfDay $opensAt,
        TimeOfDay $closesAt,
    ): self {
        return new self($id, $sodaId, $day, $opensAt, $closesAt);
    }

    /** Tell whether the slot covers a time of a day: opening included, closing excluded. */
    public function includes(DayOfWeek $day, TimeOfDay $time): bool
    {
        return $this->day->equals($day)
            && ! $time->isBefore($this->opensAt)
            && $time->isBefore($this->closesAt);
    }

    /** Tell whether both slots share a day and some minute, the closing excluded. */
    public function overlaps(self $other): bool
    {
        return $this->day->equals($other->day)
            && $this->opensAt->isBefore($other->closesAt)
            && $other->opensAt->isBefore($this->closesAt);
    }
}
