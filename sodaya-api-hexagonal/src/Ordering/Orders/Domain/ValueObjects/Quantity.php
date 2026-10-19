<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\ValueObjects;

use Src\Ordering\Orders\Domain\Exceptions\QuantityOutOfRangeException;

final readonly class Quantity
{
    public const int MIN = 1;

    public const int MAX = 20;

    /** Accept a number of portions of one dish within the allowed range. */
    public function __construct(public int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw QuantityOutOfRangeException::create();
        }
    }
}
