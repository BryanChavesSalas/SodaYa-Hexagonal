<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Application\UseCases;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\Contracts\TimeSlotRepository;
use Src\Sodas\OpeningHours\Domain\Exceptions\TimeSlotNotFoundException;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeSlotId;

final readonly class DeleteTimeSlot
{
    /** Receive the time slot repository port. */
    public function __construct(private TimeSlotRepository $slots) {}

    /** Remove a slot from the schedule of the soda. */
    public function execute(string $slotId, string $sodaId): void
    {
        $slot = $this->slots->find(new TimeSlotId($slotId), new SodaId($sodaId))
            ?? throw TimeSlotNotFoundException::create();

        $this->slots->delete($slot);
    }
}
