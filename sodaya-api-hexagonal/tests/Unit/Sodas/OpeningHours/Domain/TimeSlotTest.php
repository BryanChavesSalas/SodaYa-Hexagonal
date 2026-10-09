<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\OpeningHours\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeOfDay;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeSlotId;

final class TimeSlotTest extends TestCase
{
    /** A slot keeps its day and its two times. */
    public function test_new_slot_keeps_its_day_and_times(): void
    {
        $slot = $this->slot(1, '08:00', '12:00');

        $this->assertSame(1, $slot->day->value);
        $this->assertSame('08:00', $slot->opensAt->value);
        $this->assertSame('12:00', $slot->closesAt->value);
    }

    /** The shortest slot lasts one minute. */
    public function test_slot_may_last_a_single_minute(): void
    {
        $this->assertSame('08:01', $this->slot(1, '08:00', '08:01')->closesAt->value);
    }

    /** The opening must come strictly before the closing. */
    #[DataProvider('invertedTimes')]
    public function test_rejects_an_opening_that_is_not_before_the_closing(string $opensAt, string $closesAt): void
    {
        try {
            $this->slot(1, $opensAt, $closesAt);
            $this->fail('An inverted slot was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('sodas.time_slot_inverted', $exception->translationKey());
        }
    }

    /**
     * Opening and closing times that do not form a slot.
     *
     * @return array<string, array{string, string}>
     */
    public static function invertedTimes(): array
    {
        return [
            'same time' => ['08:00', '08:00'],
            'closing before opening' => ['12:00', '08:00'],
        ];
    }

    /** Slots of the same day overlap when they share at least one minute. */
    #[DataProvider('slotPairs')]
    public function test_detects_overlapping_slots(string $opensAt, string $closesAt, bool $overlaps): void
    {
        $morning = $this->slot(1, '08:00', '12:00');
        $other = $this->slot(1, $opensAt, $closesAt);

        $this->assertSame($overlaps, $morning->overlaps($other));
        $this->assertSame($overlaps, $other->overlaps($morning));
    }

    /**
     * Slots compared against 08:00 to 12:00 and whether they collide with it.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function slotPairs(): array
    {
        return [
            'touching after the closing' => ['12:00', '15:00', false],
            'touching before the opening' => ['06:00', '08:00', false],
            'separate slot' => ['13:00', '15:00', false],
            'one shared minute at the end' => ['11:59', '15:00', true],
            'one shared minute at the start' => ['06:00', '08:01', true],
            'contained slot' => ['09:00', '10:00', true],
            'containing slot' => ['07:00', '13:00', true],
            'identical slot' => ['08:00', '12:00', true],
        ];
    }

    /** The same hours on different days never overlap. */
    public function test_slots_of_different_days_do_not_overlap(): void
    {
        $this->assertFalse($this->slot(1, '08:00', '12:00')->overlaps($this->slot(2, '08:00', '12:00')));
    }

    /** Build a slot for the scenarios. */
    private function slot(int $day, string $opensAt, string $closesAt): TimeSlot
    {
        return TimeSlot::create(
            new TimeSlotId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44'),
            new SodaId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22'),
            new DayOfWeek($day),
            new TimeOfDay($opensAt),
            new TimeOfDay($closesAt),
        );
    }
}
