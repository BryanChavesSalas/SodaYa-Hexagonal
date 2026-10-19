<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Entities;

use Src\Ordering\Orders\Domain\ValueObjects\DishId;
use Src\Ordering\Orders\Domain\ValueObjects\Quantity;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class OrderLine
{
    public const int MAX_NAME_LENGTH = 120;

    public const int MIN_PRICE = 100;

    public const int MAX_PRICE = 100_000;

    /** Freeze the name and the price the dish had when it was ordered. */
    public function __construct(
        public DishId $dishId,
        public string $dishName,
        public int $unitPrice,
        public Quantity $quantity,
    ) {
        if (trim($dishName) === '' || mb_strlen($dishName) > self::MAX_NAME_LENGTH) {
            throw new InvalidValueException('ordering.dish_name_invalid', ['max' => self::MAX_NAME_LENGTH]);
        }

        if ($unitPrice < self::MIN_PRICE || $unitPrice > self::MAX_PRICE) {
            throw new InvalidValueException('ordering.unit_price_out_of_range', ['min' => self::MIN_PRICE, 'max' => self::MAX_PRICE]);
        }
    }

    /** Amount of the line in colones: price times quantity. */
    public function subtotal(): int
    {
        return $this->unitPrice * $this->quantity->value;
    }
}
