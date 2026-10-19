<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class DiscountPercentage
{
    public const int MIN = 1;

    public const int MAX = 90;

    /** Accept a whole percentage a happy hour takes off the price. */
    public function __construct(public int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new InvalidValueException('catalog.discount_out_of_range', ['min' => self::MIN, 'max' => self::MAX]);
        }
    }
}
