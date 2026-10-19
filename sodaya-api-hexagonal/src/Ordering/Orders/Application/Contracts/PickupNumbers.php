<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Contracts;

use DateTimeImmutable;
use Src\Ordering\Orders\Domain\ValueObjects\PickupNumber;
use Src\Shared\Domain\ValueObjects\SodaId;

interface PickupNumbers
{
    /** Hand out the next pickup number of the soda for the day of the moment, in Costa Rica; the first one of a day is 1. */
    public function nextFor(SodaId $sodaId, DateTimeImmutable $moment): PickupNumber;
}
