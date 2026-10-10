<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\Contracts;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\SodaId;

interface SodaOpenStatus
{
    /** Tell whether a soda is serving at the given moment. */
    public function isOpenAt(SodaId $sodaId, DateTimeImmutable $moment): bool;
}
