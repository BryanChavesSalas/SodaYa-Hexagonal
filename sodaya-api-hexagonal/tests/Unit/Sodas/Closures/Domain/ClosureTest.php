<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\Closures\Domain;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

final class ClosureTest extends TestCase
{
    private const string TODAY = '2026-10-07';

    /** A closure can be registered for today. */
    public function test_closure_can_be_registered_for_today(): void
    {
        $closure = $this->closure(self::TODAY, 'Feriado');

        $this->assertSame(self::TODAY, $closure->date->value);
        $this->assertSame('Feriado', $closure->reason?->value);
    }

    /** A closure can be registered for a later date and without a reason. */
    public function test_closure_can_be_registered_for_a_later_date_without_a_reason(): void
    {
        $closure = $this->closure('2026-12-25');

        $this->assertSame('2026-12-25', $closure->date->value);
        $this->assertNull($closure->reason);
    }

    /** A new closure cannot be registered for a past date. */
    public function test_closure_cannot_be_registered_for_a_past_date(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('sodas.closure_date_in_past');

        $this->closure('2026-10-06');
    }

    /** A stored closure is rebuilt even after its date has passed. */
    public function test_past_closure_can_be_reconstituted(): void
    {
        $closure = Closure::reconstitute(
            new ClosureId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11'),
            new SodaId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22'),
            new ClosureDate('2020-01-01'),
            null,
        );

        $this->assertSame('2020-01-01', $closure->date->value);
    }

    /** Only real calendar dates written as YYYY-MM-DD are accepted. */
    #[DataProvider('invalidDates')]
    public function test_closure_date_rejects_invalid_values(string $value): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('sodas.closure_date_invalid');

        new ClosureDate($value);
    }

    /**
     * Values that are not a valid closure date.
     *
     * @return array<string, array{string}>
     */
    public static function invalidDates(): array
    {
        return [
            'empty' => [''],
            'day first' => ['07-10-2026'],
            'missing padding' => ['2026-10-7'],
            'impossible day' => ['2026-02-30'],
            'with time' => ['2026-10-07 10:00'],
        ];
    }

    /** The date of a moment is taken in the moment's own time zone. */
    public function test_closure_date_is_taken_from_a_moment(): void
    {
        $lateNightInCostaRica = new DateTimeImmutable('2026-10-07 23:30', new DateTimeZone('America/Costa_Rica'));

        $this->assertSame('2026-10-07', ClosureDate::fromMoment($lateNightInCostaRica)->value);
    }

    /** Dates compare in calendar order. */
    public function test_closure_dates_compare_in_calendar_order(): void
    {
        $this->assertTrue(new ClosureDate('2026-10-06')->isBefore(new ClosureDate('2026-10-07')));
        $this->assertFalse(new ClosureDate('2026-10-07')->isBefore(new ClosureDate('2026-10-07')));
        $this->assertFalse(new ClosureDate('2026-11-01')->isBefore(new ClosureDate('2026-10-31')));
    }

    /** The reason is trimmed and limited in length. */
    public function test_closure_reason_is_trimmed_and_limited(): void
    {
        $this->assertSame('Feriado', new ClosureReason('  Feriado  ')->value);
        $this->assertSame(200, mb_strlen(new ClosureReason(str_repeat('á', 200))->value));

        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('sodas.closure_reason_invalid');

        new ClosureReason(str_repeat('a', 201));
    }

    /** A blank reason is rejected. */
    public function test_closure_reason_cannot_be_blank(): void
    {
        $this->expectException(InvalidValueException::class);

        new ClosureReason('   ');
    }

    /** Build a closure registered on the reference day. */
    private function closure(string $date, ?string $reason = null): Closure
    {
        return Closure::create(
            new ClosureId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11'),
            new SodaId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22'),
            new ClosureDate($date),
            $reason === null ? null : new ClosureReason($reason),
            new ClosureDate(self::TODAY),
        );
    }
}
