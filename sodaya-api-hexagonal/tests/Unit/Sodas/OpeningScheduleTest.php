<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Src\Sodas\Schedule\Domain\ExceptionalClosure;
use Src\Sodas\Schedule\Domain\OpeningSchedule;
use Src\Sodas\Schedule\Domain\TimeSlot;

final class OpeningScheduleTest extends TestCase
{
    private function schedule(array $closures = []): OpeningSchedule
    {
        $slot = [TimeSlot::between('08:00', '17:00')];

        return new OpeningSchedule([1 => $slot, 2 => $slot, 3 => $slot, 4 => $slot, 5 => $slot], $closures);
    }

    private function cr(string $dateTime): DateTimeImmutable
    {
        return new DateTimeImmutable($dateTime, new DateTimeZone('America/Costa_Rica'));
    }

    public function test_closed_one_minute_before_opening(): void
    {
        $this->assertFalse($this->schedule()->isOpenAt($this->cr('2026-10-05 07:59')));
    }

    public function test_open_at_the_opening_minute(): void
    {
        $this->assertTrue($this->schedule()->isOpenAt($this->cr('2026-10-05 08:00')));
    }

    public function test_open_during_the_last_minute(): void
    {
        $this->assertTrue($this->schedule()->isOpenAt($this->cr('2026-10-05 16:59')));
    }

    public function test_closed_at_the_closing_minute(): void
    {
        $this->assertFalse($this->schedule()->isOpenAt($this->cr('2026-10-05 17:00')));
    }

    public function test_closed_on_a_day_without_slots(): void
    {
        $this->assertFalse($this->schedule()->isOpenAt($this->cr('2026-10-11 10:00')));
    }

    public function test_exceptional_closure_beats_the_schedule(): void
    {
        $closure = new ExceptionalClosure($this->cr('2026-10-05 00:00'), $this->cr('2026-10-06 00:00'));

        $this->assertFalse($this->schedule([$closure])->isOpenAt($this->cr('2026-10-05 10:00')));
    }

    public function test_converts_utc_to_costa_rica_time(): void
    {
        $utc = new DateTimeImmutable('2026-10-05 14:00', new DateTimeZone('UTC')); // 08:00 in Costa Rica

        $this->assertTrue($this->schedule()->isOpenAt($utc));
    }
}
