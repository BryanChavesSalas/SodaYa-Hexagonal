<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Throwable;

final readonly class ClosureDate
{
    private string $value;

    public function __construct(string $date)
    {
        try {
            $parsed = CarbonImmutable::parse($date);
        } catch (Throwable) {
            throw new InvalidValueException('closure_date_invalid');
        }

        if ($parsed->format('Y-m-d') !== $date) {
            throw new InvalidValueException('closure_date_invalid');
        }

        $today = CarbonImmutable::now('America/Costa_Rica')->startOfDay();

        if ($parsed->startOfDay()->isBefore($today)) {
            throw new InvalidValueException('closure_date_past');
        }

        $this->value = $date;
    }

    public function value(): string
    {
        return $this->value;
    }

    public static function today(): self
    {
        return new self(CarbonImmutable::now('America/Costa_Rica')->format('Y-m-d'));
    }
}
