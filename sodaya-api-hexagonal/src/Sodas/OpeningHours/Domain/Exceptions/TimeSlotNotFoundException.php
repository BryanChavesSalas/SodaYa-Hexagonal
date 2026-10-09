<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class TimeSlotNotFoundException extends NotFoundException
{
    /** Build the exception for a slot missing from the soda. */
    public static function create(): self
    {
        return new self('sodas.time_slot_not_found');
    }
}
