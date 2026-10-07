<?php

declare(strict_types=1);

namespace Src\Sodas\Schedule\Domain;

use InvalidArgumentException;

final readonly class TimeSlot
{
    /** Build a slot from minutes since midnight. */
    public function __construct(public int $opensAt, public int $closesAt)
    {
        if ($opensAt < 0 || $closesAt > 1440 || $opensAt >= $closesAt) {
            throw new InvalidArgumentException('The slot must open before it closes within one day.');
        }
    }

    /** Build a slot from "HH:MM" texts. */
    public static function between(string $opens, string $closes): self
    {
        return new self(self::toMinutes($opens), self::toMinutes($closes));
    }

    /** Open at the opening minute, closed at the closing minute: [opens, closes). */
    public function covers(int $minuteOfDay): bool
    {
        return $minuteOfDay >= $this->opensAt && $minuteOfDay < $this->closesAt;
    }

    private static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
