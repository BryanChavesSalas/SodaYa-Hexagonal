<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Application\UseCases;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\Contracts\TimeSlotRepository;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;

final readonly class ListTimeSlots
{
    /** Receive the time slot repository port. */
    public function __construct(private TimeSlotRepository $slots) {}

    /**
     * List the schedule of the soda ordered by day and opening time.
     *
     * @return list<TimeSlot>
     */
    public function execute(string $sodaId): array
    {
        return $this->slots->allOf(new SodaId($sodaId));
    }
}
