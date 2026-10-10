<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\OpeningHours\Domain;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeOfDay;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeSlotId;
use Src\Sodas\OpeningHours\Domain\ValueObjects\WeeklySchedule;

final class WeeklyScheduleTest extends TestCase
{
    private const string TIMEZONE = 'America/Costa_Rica';

    /** A Monday with two slots opens and closes on the exact minutes. */
    #[DataProvider('mondayMoments')]
    public function test_follows_the_slots_of_the_day(string $moment, bool $open): void
    {
        $schedule = new WeeklySchedule([
            $this->slot(1, '08:00', '12:00'),
            $this->slot(1, '13:00', '17:00'),
        ]);

        $this->assertSame($open, $schedule->isOpenAt($this->moment($moment)));
    }

    /**
     * Moments of Monday 2026-10-05 and whether the soda is open.
     *
     * @return array<string, array{string, bool}>
     */
    public static function mondayMoments(): array
    {
        return [
            'last second before opening' => ['2026-10-05 07:59:59', false],
            'opening minute' => ['2026-10-05 08:00:00', true],
            'middle of the first slot' => ['2026-10-05 10:30:00', true],
            'last second of the first slot' => ['2026-10-05 11:59:59', true],
            'closing minute' => ['2026-10-05 12:00:00', false],
            'between the two slots' => ['2026-10-05 12:30:00', false],
            'opening minute of the second slot' => ['2026-10-05 13:00:00', true],
            'closing minute of the second slot' => ['2026-10-05 17:00:00', false],
        ];
    }

    /** A day without slots is closed all day. */
    public function test_day_without_slots_is_closed(): void
    {
        $schedule = new WeeklySchedule([$this->slot(1, '08:00', '12:00')]);

        $this->assertFalse($schedule->isOpenAt($this->moment('2026-10-06 10:00:00')));
        $this->assertFalse(new WeeklySchedule([])->isOpenAt($this->moment('2026-10-05 10:00:00')));
    }

    /** Day 7 is Sunday. */
    public function test_sunday_slot_opens_on_sunday(): void
    {
        $schedule = new WeeklySchedule([$this->slot(7, '08:00', '12:00')]);

        $this->assertTrue($schedule->isOpenAt($this->moment('2026-10-11 10:00:00')));
        $this->assertFalse($schedule->isOpenAt($this->moment('2026-10-10 10:00:00')));
    }

    /** The day and the time are read in the time zone the moment carries. */
    public function test_moment_is_read_in_its_own_time_zone(): void
    {
        $schedule = new WeeklySchedule([$this->slot(1, '20:00', '23:30')]);
        $mondayNight = $this->moment('2026-10-05 23:00:00');

        $this->assertTrue($schedule->isOpenAt($mondayNight));
        $this->assertFalse($schedule->isOpenAt($mondayNight->setTimezone(new DateTimeZone('UTC'))));
    }

    /** Build a moment in Costa Rica time. */
    private function moment(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone(self::TIMEZONE));
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
