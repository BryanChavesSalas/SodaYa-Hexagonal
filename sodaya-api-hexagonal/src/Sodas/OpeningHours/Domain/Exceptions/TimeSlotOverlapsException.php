<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class TimeSlotOverlapsException extends DomainException
{
    /** Build the exception for a slot that collides with another of the same day. */
    public static function create(): self
    {
        return new self('sodas.time_slot_overlaps');
    }
}
