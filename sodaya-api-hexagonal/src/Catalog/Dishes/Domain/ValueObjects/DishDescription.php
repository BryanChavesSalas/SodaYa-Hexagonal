<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class DishDescription
{
    public const int MAX_LENGTH = 500;

    public string $value;

    /** Trim the description and reject empty or oversized values. */
    public function __construct(string $value)
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueException('catalog.description_invalid', ['max' => self::MAX_LENGTH]);
        }

        $this->value = $value;
    }
}
