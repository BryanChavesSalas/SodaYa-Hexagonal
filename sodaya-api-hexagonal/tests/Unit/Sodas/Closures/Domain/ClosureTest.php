<?php

declare(strict_types=1);

namespace Tests\Unit\Sodas\Closures\Domain;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Closure;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

final class ClosureTest extends TestCase
{
    public function test_models_an_exceptional_closure(): void
    {
        $today = ClosureDate::today();
        $closure = Closure::create(
            new ClosureId((string) Str::uuid7()),
            new SodaId((string) Str::uuid7()),
            $today,
            new ClosureReason('Mantenimiento')
        );

        $this->assertSame($today->value(), $closure->date()->value());
        $this->assertSame('Mantenimiento', $closure->reason()->value());
    }

    public function test_rejects_a_past_closure_date(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('closure_date_past');

        new ClosureDate('2020-01-01');
    }

    public function test_rejects_a_reason_longer_than_200_characters(): void
    {
        $this->expectException(InvalidValueException::class);
        $this->expectExceptionMessage('closure_reason_too_long');

        new ClosureReason(str_repeat('a', 201));
    }
}
