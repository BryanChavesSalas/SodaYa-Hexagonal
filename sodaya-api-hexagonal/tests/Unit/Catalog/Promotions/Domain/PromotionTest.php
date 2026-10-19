<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Promotions\Domain;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Catalog\Promotions\Domain\Entities\Promotion;
use Src\Catalog\Promotions\Domain\ValueObjects\DiscountPercentage;
use Src\Catalog\Promotions\Domain\ValueObjects\PromotionId;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeOfDay;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeWindow;
use Src\Catalog\Promotions\Domain\ValueObjects\Weekday;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;

final class PromotionTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d23';

    /** Values on the boundaries of each range are accepted. */
    public function test_accepts_boundary_values(): void
    {
        $this->assertSame(1, new Weekday(1)->value);
        $this->assertSame(7, new Weekday(7)->value);
        $this->assertSame(1, new DiscountPercentage(1)->value);
        $this->assertSame(90, new DiscountPercentage(90)->value);
        $this->assertSame('23:59', new TimeWindow(new TimeOfDay('23:58'), new TimeOfDay('23:59'))->endsAt->value);
    }

    /** Out-of-range values and inverted windows are rejected with a translatable error. */
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
            'day before monday' => [fn () => new Weekday(0), 'catalog.weekday_out_of_range'],
            'day after sunday' => [fn () => new Weekday(8), 'catalog.weekday_out_of_range'],
            'no discount' => [fn () => new DiscountPercentage(0), 'catalog.discount_out_of_range'],
            'discount above ninety' => [fn () => new DiscountPercentage(91), 'catalog.discount_out_of_range'],
            'ninety-five percent' => [fn () => new DiscountPercentage(95), 'catalog.discount_out_of_range'],
            'hour 24' => [fn () => new TimeOfDay('24:00'), 'catalog.time_of_day_invalid'],
            'time with seconds' => [fn () => new TimeOfDay('14:00:00'), 'catalog.time_of_day_invalid'],
            'window that ends when it starts' => [fn () => new TimeWindow(new TimeOfDay('14:00'), new TimeOfDay('14:00')), 'catalog.promotion_window_inverted'],
            'window that ends before it starts' => [fn () => new TimeWindow(new TimeOfDay('15:00'), new TimeOfDay('14:00')), 'catalog.promotion_window_inverted'],
        ];
    }

    /** The happy hour applies from its first minute and no longer at its end minute, on its day only, in Costa Rica time. */
    #[DataProvider('moments')]
    public function test_is_active_from_its_start_and_not_at_its_end(string $moment, bool $active): void
    {
        $promotion = $this->promotion(3, '14:00', '15:00');

        $this->assertSame($active, $promotion->isActiveAt(new DateTimeImmutable($moment, new DateTimeZone('America/Costa_Rica'))));
    }

    /**
     * Moments around a Wednesday happy hour of 14:00 to 15:00 and whether it applies.
     *
     * @return array<string, array{string, bool}>
     */
    public static function moments(): array
    {
        return [
            'minute before the start' => ['2026-10-21 13:59:59', false],
            'start minute' => ['2026-10-21 14:00:00', true],
            'last minute' => ['2026-10-21 14:59:59', true],
            'end minute' => ['2026-10-21 15:00:00', false],
            'same time on thursday' => ['2026-10-22 14:30:00', false],
        ];
    }

    /** A moment given in UTC is read on the clock of Costa Rica, not on the one of the server. */
    public function test_reads_the_moment_in_its_own_time_zone(): void
    {
        $promotion = $this->promotion(3, '14:00', '15:00');
        $utc = new DateTimeImmutable('2026-10-21 20:30:00', new DateTimeZone('UTC'));

        $this->assertFalse($promotion->isActiveAt($utc));
        $this->assertTrue($promotion->isActiveAt($utc->setTimezone(new DateTimeZone('America/Costa_Rica'))));
    }

    /** Two happy hours overlap when they share a soda, a day and at least one minute; the end minute is not shared. */
    #[DataProvider('pairs')]
    public function test_detects_overlapping_promotions(int $weekday, string $startsAt, string $endsAt, string $sodaId, bool $overlaps): void
    {
        $afternoon = $this->promotion(3, '14:00', '15:00');
        $other = $this->promotion($weekday, $startsAt, $endsAt, $sodaId);

        $this->assertSame($overlaps, $afternoon->overlaps($other));
        $this->assertSame($overlaps, $other->overlaps($afternoon));
    }

    /**
     * Happy hours compared against Wednesday 14:00 to 15:00 of the soda, and whether they collide with it.
     *
     * @return array<string, array{int, string, string, string, bool}>
     */
    public static function pairs(): array
    {
        return [
            'half an hour later' => [3, '14:30', '16:00', self::SODA_ID, true],
            'touching at the end' => [3, '15:00', '16:00', self::SODA_ID, false],
            'touching at the start' => [3, '13:00', '14:00', self::SODA_ID, false],
            'same window on thursday' => [4, '14:00', '15:00', self::SODA_ID, false],
            'same window of another soda' => [3, '14:00', '15:00', self::OTHER_SODA_ID, false],
        ];
    }

    /** Build a happy hour of 20 % for the scenarios. */
    private function promotion(int $weekday, string $startsAt, string $endsAt, string $sodaId = self::SODA_ID): Promotion
    {
        return Promotion::create(
            new PromotionId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44'),
            new SodaId($sodaId),
            new Weekday($weekday),
            new TimeWindow(new TimeOfDay($startsAt), new TimeOfDay($endsAt)),
            new DiscountPercentage(20),
        );
    }
}
