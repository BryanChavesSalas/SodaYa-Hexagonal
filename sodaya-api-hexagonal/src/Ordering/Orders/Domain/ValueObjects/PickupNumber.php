<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class PickupNumber
{
    public const int MIN = 1;

    public const int MAX = 9999;

    /** Accept the short number the counter calls out for an order, consecutive per soda and day. */
    public function __construct(public int $value)
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new InvalidValueException('ordering.pickup_number_out_of_range', ['min' => self::MIN, 'max' => self::MAX]);
        }
    }
}
