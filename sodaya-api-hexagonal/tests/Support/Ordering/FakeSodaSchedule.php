<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\SodaSchedule;
use Src\Shared\Domain\ValueObjects\SodaId;

final class FakeSodaSchedule implements SodaSchedule
{
    /** Decide once, for the whole scenario, whether the soda is open. */
    public function __construct(public bool $open = true) {}

    /** Answer what the scenario decided. */
    public function isOpenAt(SodaId $sodaId, DateTimeImmutable $moment): bool
    {
        return $this->open;
    }
}
