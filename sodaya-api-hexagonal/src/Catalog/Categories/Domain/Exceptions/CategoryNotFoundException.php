<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class CategoryNotFoundException extends NotFoundException
{
    /** Build the exception for a category missing from the soda. */
    public static function create(): self
    {
        return new self('catalog.category_not_found');
    }
}
