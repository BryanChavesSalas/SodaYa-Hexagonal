<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\SodaSchedule;
use Src\Ordering\Orders\Domain\Exceptions\SodaNotFoundException;
use Src\Shared\Domain\ValueObjects\SodaId;

final class FakeSodaSchedule implements SodaSchedule
{
    /** Decide once, for the whole scenario, whether the soda exists and whether it is open. */
    public function __construct(public bool $open = true, public bool $exists = true) {}

    /** Answer what the scenario decided. */
    public function isOpenAt(SodaId $sodaId, DateTimeImmutable $moment): bool
    {
        if (! $this->exists) {
            throw SodaNotFoundException::create();
        }

        return $this->open;
    }
}
