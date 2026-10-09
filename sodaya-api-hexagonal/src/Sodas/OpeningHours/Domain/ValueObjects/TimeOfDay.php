<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class TimeOfDay
{
    private const string PATTERN = '/^(?:[01]\d|2[0-3]):[0-5]\d$/';

    /** Accept a 24-hour time written as HH:MM, from 00:00 to 23:59. */
    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidValueException('sodas.time_of_day_invalid');
        }
    }

    /** Tell whether this time comes strictly before the other. */
    public function isBefore(self $other): bool
    {
        return strcmp($this->value, $other->value) < 0;
    }
}
