<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Price
{
    public const int MIN = 100;

    public const int MAX = 100_000;

    /** Accept whole colones within the allowed range. */
    public function __construct(public int $amount)
    {
        if ($amount < self::MIN || $amount > self::MAX) {
            throw new InvalidValueException('catalog.price_out_of_range', ['min' => self::MIN, 'max' => self::MAX]);
        }
    }
}
