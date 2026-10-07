<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class TimeOfDay
{
    public int $minutes;

    /** Accept an `HH:MM` wall-clock time in the soda's time zone (America/Costa_Rica). */
    public function __construct(string $value)
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $parts) !== 1) {
            throw new InvalidValueException('schedules.time_invalid');
        }

        $this->minutes = (int) $parts[1] * 60 + (int) $parts[2];
    }

    /** Render the time as `HH:MM`. */
    public function format(): string
    {
        return sprintf('%02d:%02d', intdiv($this->minutes, 60), $this->minutes % 60);
    }

    /** Tell whether this time is strictly earlier than the other. */
    public function isBefore(self $other): bool
    {
        return $this->minutes < $other->minutes;
    }
}
