<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class DishNameAlreadyInUseException extends InvalidValueException
{
    /** Build the exception for a name taken by another dish of the soda. */
    public static function create(): self
    {
        return new self('catalog.dish_name_in_use');
    }
}
