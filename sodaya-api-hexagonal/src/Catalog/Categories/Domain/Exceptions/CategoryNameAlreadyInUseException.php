<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class CategoryNameAlreadyInUseException extends InvalidValueException
{
    /** Build the exception for a name taken by another category of the soda. */
    public static function create(): self
    {
        return new self('catalog.category_name_in_use');
    }
}
