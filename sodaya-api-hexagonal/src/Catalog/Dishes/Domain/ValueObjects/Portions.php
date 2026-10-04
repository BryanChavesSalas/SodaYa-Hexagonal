<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Portions
{
    public const int MIN = 0;

    public const int MAX = 500;

    /** Accept a portion count within the allowed range. */
    public function __construct(public int $quantity)
    {
        if ($quantity < self::MIN || $quantity > self::MAX) {
            throw new InvalidValueException('catalog.portions_out_of_range', ['min' => self::MIN, 'max' => self::MAX]);
        }
    }
}
