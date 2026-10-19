<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Application\Contracts;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\SodaId;

interface ActivePromotion
{
    /** Percentage the happy hour of the soda takes off at the moment, in Costa Rica time; 0 when none applies. */
    public function percentageFor(SodaId $sodaId, DateTimeImmutable $moment): int;
}
