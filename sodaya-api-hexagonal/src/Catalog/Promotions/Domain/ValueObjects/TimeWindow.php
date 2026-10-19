<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class TimeWindow
{
    /** Accept a window whose start comes strictly before its end. */
    public function __construct(
        public TimeOfDay $startsAt,
        public TimeOfDay $endsAt,
    ) {
        if (! $startsAt->isBefore($endsAt)) {
            throw new InvalidValueException('catalog.promotion_window_inverted');
        }
    }

    /** Tell whether the window covers a time: the start belongs to it, the end does not. */
    public function includes(TimeOfDay $time): bool
    {
        return ! $time->isBefore($this->startsAt) && $time->isBefore($this->endsAt);
    }

    /** Tell whether both windows share some minute, the ends excluded. */
    public function overlaps(self $other): bool
    {
        return $this->startsAt->isBefore($other->endsAt) && $other->startsAt->isBefore($this->endsAt);
    }
}
