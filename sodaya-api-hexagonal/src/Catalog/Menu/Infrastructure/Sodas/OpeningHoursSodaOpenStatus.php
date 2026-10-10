<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Sodas;

use DateTimeImmutable;
use Src\Catalog\Menu\Application\Contracts\SodaOpenStatus;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Application\UseCases\CheckSodaIsOpen;

final readonly class OpeningHoursSodaOpenStatus implements SodaOpenStatus
{
    /** Receive the use case of the Sodas context that knows the schedule. */
    public function __construct(private CheckSodaIsOpen $checkSodaIsOpen) {}

    /** Ask the Sodas context on every call, without caching the answer. */
    public function isOpenAt(SodaId $sodaId, DateTimeImmutable $moment): bool
    {
        return $this->checkSodaIsOpen->execute($sodaId->value, $moment);
    }
}
