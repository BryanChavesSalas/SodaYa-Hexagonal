<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PreparationTime
{
    public const int MIN = 1;

    public const int MAX = 120;

    /** Accept minutes within the allowed range. */
    public function __construct(public int $minutes)
    {
        if ($minutes < self::MIN || $minutes > self::MAX) {
            throw new InvalidValueException('catalog.preparation_time_out_of_range', ['min' => self::MIN, 'max' => self::MAX]);
        }
    }
}
