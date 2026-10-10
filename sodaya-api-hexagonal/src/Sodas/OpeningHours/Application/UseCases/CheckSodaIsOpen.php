<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Application\UseCases;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepository;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\OpeningHours\Domain\Contracts\TimeSlotRepository;
use Src\Sodas\OpeningHours\Domain\ValueObjects\WeeklySchedule;

final readonly class CheckSodaIsOpen
{
    /** Receive the time slot and closure repository ports. */
    public function __construct(
        private TimeSlotRepository $slots,
        private ClosureRepository $closures,
    ) {}

    /** Tell whether the soda is open at the moment; a closure on that date wins over the schedule. */
    public function execute(string $sodaId, DateTimeImmutable $moment): bool
    {
        $soda = new SodaId($sodaId);

        if ($this->closures->onDate($soda, ClosureDate::fromMoment($moment)) !== null) {
            return false;
        }

        return new WeeklySchedule($this->slots->allOf($soda))->isOpenAt($moment);
    }
}
