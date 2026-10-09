<?php

declare(strict_types=1);

namespace Tests\Support\Sodas;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\Contracts\TimeSlotRepository;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeSlotId;

final class InMemoryTimeSlotRepository implements TimeSlotRepository
{
    /** @var array<string, TimeSlot> */
    private array $slots = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): TimeSlotId
    {
        return new TimeSlotId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store the slot without any check of its own. */
    public function save(TimeSlot $slot): void
    {
        $this->slots[$slot->id->value] = $slot;
    }

    /** Find the slot scoped to its soda. */
    public function find(TimeSlotId $id, SodaId $sodaId): ?TimeSlot
    {
        $slot = $this->slots[$id->value] ?? null;

        return $slot?->sodaId->equals($sodaId) === true ? $slot : null;
    }

    /**
     * List the slots of the soda ordered by day and opening time.
     *
     * @return list<TimeSlot>
     */
    public function allOf(SodaId $sodaId): array
    {
        $slots = array_filter($this->slots, fn (TimeSlot $slot): bool => $slot->sodaId->equals($sodaId));

        usort($slots, fn (TimeSlot $a, TimeSlot $b): int => [$a->day->value, $a->opensAt->value]
            <=> [$b->day->value, $b->opensAt->value]);

        return $slots;
    }

    /**
     * List the slots of the soda on one day ordered by opening time.
     *
     * @return list<TimeSlot>
     */
    public function ofDay(SodaId $sodaId, DayOfWeek $day): array
    {
        return array_values(array_filter(
            $this->allOf($sodaId),
            fn (TimeSlot $slot): bool => $slot->day->equals($day),
        ));
    }

    /** Forget the slot. */
    public function delete(TimeSlot $slot): void
    {
        unset($this->slots[$slot->id->value]);
    }
}
