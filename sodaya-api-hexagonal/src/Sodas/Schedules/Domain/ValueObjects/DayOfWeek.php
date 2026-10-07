<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class DayOfWeek
{
    public const int MONDAY = 1;

    public const int SUNDAY = 7;

    /** Accept a day number where 1 is Monday and 7 is Sunday. */
    public function __construct(public int $number)
    {
        if ($number < self::MONDAY || $number > self::SUNDAY) {
            throw new InvalidValueException('schedules.day_out_of_range', ['min' => self::MONDAY, 'max' => self::SUNDAY]);
        }
    }

    /** Compare two days by value. */
    public function equals(self $other): bool
    {
        return $other->number === $this->number;
    }
}
