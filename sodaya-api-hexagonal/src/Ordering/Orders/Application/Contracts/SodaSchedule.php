<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use DateTimeImmutable;
use Src\Ordering\Orders\Domain\Exceptions\SodaNotFoundException;
use Src\Shared\Domain\ValueObjects\SodaId;

interface SodaSchedule
{
    /**
     * Tell whether the soda receives orders at the given moment.
     *
     * @throws SodaNotFoundException when the soda does not exist
     */
    public function isOpenAt(SodaId $sodaId, DateTimeImmutable $moment): bool;
}
