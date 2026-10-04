<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class DishNotFoundException extends NotFoundException
{
    /** Build the exception for a dish missing from the soda. */
    public static function create(): self
    {
        return new self('catalog.dish_not_found');
    }
}
