<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\OpeningHours\Domain;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeOfDay;

final class ValueObjectsTest extends TestCase
{
    /** Values on the boundaries of each range are accepted. */
    public function test_accepts_boundary_values(): void
    {
        $this->assertSame(1, new DayOfWeek(1)->value);
        $this->assertSame(7, new DayOfWeek(7)->value);
        $this->assertSame('00:00', new TimeOfDay('00:00')->value);
        $this->assertSame('23:59', new TimeOfDay('23:59')->value);
    }

    /** Day 1 is Monday and day 7 is Sunday. */
    public function test_week_starts_on_monday_and_ends_on_sunday(): void
    {
        $monday = new DateTimeImmutable('2026-10-05 10:00:00');
        $sunday = new DateTimeImmutable('2026-10-11 10:00:00');

        $this->assertSame('Monday', $monday->format('l'));
        $this->assertSame(1, DayOfWeek::fromMoment($monday)->value);
        $this->assertSame('Sunday', $sunday->format('l'));
        $this->assertSame(7, DayOfWeek::fromMoment($sunday)->value);
    }

    /** Times compare by the clock and a time is not before itself. */
    public function test_times_are_comparable(): void
    {
        $this->assertTrue(new TimeOfDay('08:59')->isBefore(new TimeOfDay('09:00')));
        $this->assertFalse(new TimeOfDay('09:00')->isBefore(new TimeOfDay('08:59')));
        $this->assertFalse(new TimeOfDay('09:00')->isBefore(new TimeOfDay('09:00')));
    }

    /** Out-of-range values are rejected with a translatable error. */
    #[DataProvider('invalidValues')]
    public function test_rejects_invalid_values(Closure $build, string $translationKey): void
    {
        try {
            $build();
            $this->fail('An invalid value was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame($translationKey, $exception->translationKey());
        }
    }

    /**
     * Builders of invalid value objects and the error each one raises.
     *
     * @return array<string, array{Closure, string}>
     */
    public static function invalidValues(): array
    {
        return [
            'day before monday' => [fn () => new DayOfWeek(0), 'sodas.day_of_week_out_of_range'],
            'day after sunday' => [fn () => new DayOfWeek(8), 'sodas.day_of_week_out_of_range'],
            'hour 24' => [fn () => new TimeOfDay('24:00'), 'sodas.time_of_day_invalid'],
            'minute 60' => [fn () => new TimeOfDay('12:60'), 'sodas.time_of_day_invalid'],
            'hour without padding' => [fn () => new TimeOfDay('8:00'), 'sodas.time_of_day_invalid'],
            'time with seconds' => [fn () => new TimeOfDay('08:00:00'), 'sodas.time_of_day_invalid'],
        ];
    }
}
