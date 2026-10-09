<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Application\UseCases;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Application\DTOs\AddTimeSlotCommand;
use Src\Sodas\OpeningHours\Domain\Contracts\TimeSlotRepository;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;
use Src\Sodas\OpeningHours\Domain\Exceptions\TimeSlotOverlapsException;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeOfDay;

final readonly class AddTimeSlot
{
    /** Receive the time slot repository port. */
    public function __construct(private TimeSlotRepository $slots) {}

    /** Add a slot to the schedule unless it overlaps another of the same day. */
    public function execute(AddTimeSlotCommand $command): TimeSlot
    {
        $slot = TimeSlot::create(
            $this->slots->nextId(),
            new SodaId($command->sodaId),
            new DayOfWeek($command->day),
            new TimeOfDay($command->opensAt),
            new TimeOfDay($command->closesAt),
        );

        if (array_any($this->slots->ofDay($slot->sodaId, $slot->day), $slot->overlaps(...))) {
            throw TimeSlotOverlapsException::create();
        }

        $this->slots->save($slot);

        return $slot;
    }
}
