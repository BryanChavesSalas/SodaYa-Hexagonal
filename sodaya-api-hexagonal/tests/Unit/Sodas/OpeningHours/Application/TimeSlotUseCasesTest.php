<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\OpeningHours\Application;

use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Sodas\OpeningHours\Application\DTOs\AddTimeSlotCommand;
use Src\Sodas\OpeningHours\Application\UseCases\AddTimeSlot;
use Src\Sodas\OpeningHours\Application\UseCases\DeleteTimeSlot;
use Src\Sodas\OpeningHours\Application\UseCases\ListTimeSlots;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;
use Src\Sodas\OpeningHours\Domain\Exceptions\TimeSlotNotFoundException;
use Src\Sodas\OpeningHours\Domain\Exceptions\TimeSlotOverlapsException;
use Tests\Support\Sodas\InMemoryTimeSlotRepository;

final class TimeSlotUseCasesTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d23';

    private InMemoryTimeSlotRepository $slots;

    /** Start every scenario with an empty in-memory repository. */
    protected function setUp(): void
    {
        $this->slots = new InMemoryTimeSlotRepository;
    }

    /** A day accepts several slots and the listing follows the week and the clock. */
    public function test_day_accepts_several_slots_listed_in_order(): void
    {
        $this->add(2, '08:00', '12:00');
        $this->add(1, '12:00', '15:00');
        $this->add(1, '08:00', '12:00');
        $this->add(1, '08:00', '12:00', self::OTHER_SODA_ID);

        $schedule = array_map(
            fn (TimeSlot $slot): string => "{$slot->day->value} {$slot->opensAt->value}-{$slot->closesAt->value}",
            new ListTimeSlots($this->slots)->execute(self::SODA_ID),
        );

        $this->assertSame(['1 08:00-12:00', '1 12:00-15:00', '2 08:00-12:00'], $schedule);
    }

    /** A slot that shares a minute with another of the same day is rejected. */
    public function test_rejects_a_slot_that_overlaps_another_of_the_same_day(): void
    {
        $this->add(1, '08:00', '12:00');

        try {
            $this->add(1, '11:00', '15:00');
            $this->fail('An overlapping slot was accepted.');
        } catch (TimeSlotOverlapsException) {
            $this->assertCount(1, new ListTimeSlots($this->slots)->execute(self::SODA_ID));
        }
    }

    /** An inverted slot is rejected before anything is stored. */
    public function test_rejects_an_inverted_slot(): void
    {
        try {
            $this->add(1, '12:00', '08:00');
            $this->fail('An inverted slot was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('sodas.time_slot_inverted', $exception->translationKey());
            $this->assertSame([], new ListTimeSlots($this->slots)->execute(self::SODA_ID));
        }
    }

    /** Deleting removes the slot from the schedule. */
    public function test_deletes_a_slot_of_the_soda(): void
    {
        $slot = $this->add(1, '08:00', '12:00');

        new DeleteTimeSlot($this->slots)->execute($slot->id->value, self::SODA_ID);

        $this->assertSame([], new ListTimeSlots($this->slots)->execute(self::SODA_ID));
    }

    /** A slot of another soda cannot be deleted. */
    public function test_cannot_delete_a_slot_of_another_soda(): void
    {
        $slot = $this->add(1, '08:00', '12:00', self::OTHER_SODA_ID);

        try {
            new DeleteTimeSlot($this->slots)->execute($slot->id->value, self::SODA_ID);
            $this->fail('A slot of another soda was deleted.');
        } catch (TimeSlotNotFoundException) {
            $this->assertCount(1, new ListTimeSlots($this->slots)->execute(self::OTHER_SODA_ID));
        }
    }

    /** Add a slot through the use case. */
    private function add(int $day, string $opensAt, string $closesAt, string $sodaId = self::SODA_ID): TimeSlot
    {
        return new AddTimeSlot($this->slots)->execute(new AddTimeSlotCommand($sodaId, $day, $opensAt, $closesAt));
    }
}
