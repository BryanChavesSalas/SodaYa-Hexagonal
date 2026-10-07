<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class InvalidTimeRangeException extends InvalidValueException
{
    /** Build the exception for an opening that is not before the closing. */
    public static function create(): self
    {
        return new self('schedules.opens_not_before_closes');
    }
}
