<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Closure;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

test('models an exceptional closure', function () {
    $today = CarbonImmutable::now('America/Costa_Rica')->format('Y-m-d');
    $closure = Closure::create(
        new ClosureId((string) Str::uuid7()),
        new SodaId((string) Str::uuid7()),
        new ClosureDate($today),
        new ClosureReason('Mantenimiento')
    );

    expect($closure->date()->value())->toBe($today)
        ->and($closure->reason()->value())->toBe('Mantenimiento');
});

test('rejects a past closure date', function () {
    new ClosureDate('2020-01-01');
})->throws(InvalidValueException::class, 'closure_date_past');

test('rejects a reason longer than 200 characters', function () {
    new ClosureReason(str_repeat('a', 201));
})->throws(InvalidValueException::class, 'closure_reason_too_long');
