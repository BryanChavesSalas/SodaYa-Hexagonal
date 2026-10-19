<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Sodas;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\SodaSchedule;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Application\UseCases\CheckSodaIsOpen;

final readonly class OpeningHoursSodaSchedule implements SodaSchedule
{
    /** Receive the use case of the Sodas context that knows the schedule and the closures. */
    public function __construct(private CheckSodaIsOpen $checkSodaIsOpen) {}

    /** Ask the Sodas context whether the soda is open at the moment. */
    public function isOpenAt(SodaId $sodaId, DateTimeImmutable $moment): bool
    {
        return $this->checkSodaIsOpen->execute($sodaId->value, $moment);
    }
}
