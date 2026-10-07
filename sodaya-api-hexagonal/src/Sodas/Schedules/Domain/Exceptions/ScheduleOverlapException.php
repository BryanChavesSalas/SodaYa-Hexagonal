<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class ScheduleOverlapException extends DomainException
{
    /** Build the exception for a slot that overlaps another of the same day. */
    public static function create(): self
    {
        return new self('schedules.overlap');
    }
}
