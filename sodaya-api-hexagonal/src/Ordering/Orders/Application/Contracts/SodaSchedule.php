<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\SodaId;

interface SodaSchedule
{
    /** Tell whether the soda receives orders at the given moment. */
    public function isOpenAt(SodaId $sodaId, DateTimeImmutable $moment): bool;
}
