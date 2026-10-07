<?php

declare(strict_types=1);

namespace Src\Sodas\Schedule\Domain;

use DateTimeImmutable;

final readonly class ExceptionalClosure
{
    /** Describe a period when the soda stays closed whatever its schedule says. */
    public function __construct(public DateTimeImmutable $from, public DateTimeImmutable $until) {}

    /** The closure starts at "from" and ends right before "until". */
    public function covers(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->from && $moment < $this->until;
    }
}
