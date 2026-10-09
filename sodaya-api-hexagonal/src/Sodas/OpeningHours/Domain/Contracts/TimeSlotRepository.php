<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;
use Src\Sodas\OpeningHours\Domain\Exceptions\TimeSlotOverlapsException;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeSlotId;

interface TimeSlotRepository
{
    /** Generate the identity for a new slot. */
    public function nextId(): TimeSlotId;

    /**
     * Persist a new slot.
     *
     * @throws TimeSlotOverlapsException
     */
    public function save(TimeSlot $slot): void;

    /** Find a slot that belongs to the given soda. */
    public function find(TimeSlotId $id, SodaId $sodaId): ?TimeSlot;

    /**
     * List every slot of a soda ordered by day and opening time.
     *
     * @return list<TimeSlot>
     */
    public function allOf(SodaId $sodaId): array;

    /**
     * List the slots of a soda on one day of the week.
     *
     * @return list<TimeSlot>
     */
    public function ofDay(SodaId $sodaId, DayOfWeek $day): array;

    /** Remove a slot from the schedule. */
    public function delete(TimeSlot $slot): void;
}
