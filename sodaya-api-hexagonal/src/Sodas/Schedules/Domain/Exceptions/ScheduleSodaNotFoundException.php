<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class ScheduleSodaNotFoundException extends NotFoundException
{
    /** Build the exception for a soda that does not exist. */
    public static function create(): self
    {
        return new self('schedules.soda_not_found');
    }
}
