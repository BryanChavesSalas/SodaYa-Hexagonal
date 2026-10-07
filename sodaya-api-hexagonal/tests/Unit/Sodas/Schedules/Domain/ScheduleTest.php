<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\Schedules\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Schedules\Domain\Entities\Schedule;
use Src\Sodas\Schedules\Domain\Exceptions\InvalidTimeRangeException;
use Src\Sodas\Schedules\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\Schedules\Domain\ValueObjects\ScheduleId;
use Src\Sodas\Schedules\Domain\ValueObjects\TimeOfDay;

final class ScheduleTest extends TestCase
{
    private const string SODA = '0198a1b2-c3d4-7e5f-8a6b-7c8d9e0f1a2b';

    private const string OTHER_SODA = '0198a1b2-c3d4-7e5f-8a6b-7c8d9e0f1a2c';

    private function slot(string $opens, string $closes, int $day = 1, string $soda = self::SODA): Schedule
    {
        return new Schedule(
            new ScheduleId('0198a1b2-c3d4-7e5f-8a6b-7c8d9e0f1a00'),
            new SodaId($soda),
            new DayOfWeek($day),
            new TimeOfDay($opens),
            new TimeOfDay($closes),
        );
    }

    /** A slot with the opening before the closing is accepted. */
    public function test_accepts_a_valid_slot(): void
    {
        $schedule = $this->slot('07:00', '14:30');

        $this->assertSame('07:00', $schedule->opensAt->format());
        $this->assertSame('14:30', $schedule->closesAt->format());
        $this->assertSame(1, $schedule->day->number);
    }

    /** An opening equal to or after the closing is rejected. */
    #[DataProvider('invalidRanges')]
    public function test_rejects_opening_not_before_closing(string $opens, string $closes): void
    {
        try {
            $this->slot($opens, $closes);
            $this->fail('An invalid range was accepted.');
        } catch (InvalidTimeRangeException $exception) {
            $this->assertSame('schedules.opens_not_before_closes', $exception->translationKey());
        }
    }

    /** @return array<string, array{string, string}> */
    public static function invalidRanges(): array
    {
        return ['equal' => ['08:00', '08:00'], 'reversed' => ['18:00', '08:00']];
    }

    /** Days run from 1 (Monday) to 7 (Sunday). */
    public function test_day_boundaries(): void
    {
        $this->assertSame(1, new DayOfWeek(1)->number);
        $this->assertSame(7, new DayOfWeek(7)->number);

        foreach ([0, 8, -1] as $invalid) {
            try {
                new DayOfWeek($invalid);
                $this->fail('An invalid day was accepted.');
            } catch (InvalidValueException $exception) {
                $this->assertSame('schedules.day_out_of_range', $exception->translationKey());
            }
        }
    }

    /** Only well-formed 24-hour times are accepted. */
    public function test_rejects_malformed_times(): void
    {
        $this->assertSame(0, new TimeOfDay('00:00')->minutes);
        $this->assertSame(1439, new TimeOfDay('23:59')->minutes);

        foreach (['24:00', '7:00', '12:60', 'abc', ''] as $invalid) {
            try {
                new TimeOfDay($invalid);
                $this->fail("'{$invalid}' was accepted.");
            } catch (InvalidValueException $exception) {
                $this->assertSame('schedules.time_invalid', $exception->translationKey());
            }
        }
    }

    /** Slots overlap when they share time; touching edges do not, because closing is excluded. */
    #[DataProvider('overlapCases')]
    public function test_overlap_rules(string $opens, string $closes, bool $overlaps): void
    {
        $base = $this->slot('08:00', '12:00');

        $this->assertSame($overlaps, $base->overlaps($this->slot($opens, $closes)));
        $this->assertSame($overlaps, $this->slot($opens, $closes)->overlaps($base));
    }

    /** @return array<string, array{string, string, bool}> */
    public static function overlapCases(): array
    {
        return [
            'partial' => ['11:00', '13:00', true],
            'contained' => ['09:00', '10:00', true],
            'containing' => ['07:00', '13:00', true],
            'identical' => ['08:00', '12:00', true],
            'adjacent after' => ['12:00', '15:00', false],
            'adjacent before' => ['06:00', '08:00', false],
            'separate' => ['14:00', '18:00', false],
        ];
    }

    /** Slots of another day or another soda never overlap. */
    public function test_other_day_or_soda_does_not_overlap(): void
    {
        $base = $this->slot('08:00', '12:00');

        $this->assertFalse($base->overlaps($this->slot('08:00', '12:00', day: 2)));
        $this->assertFalse($base->overlaps($this->slot('08:00', '12:00', soda: self::OTHER_SODA)));
    }
}
