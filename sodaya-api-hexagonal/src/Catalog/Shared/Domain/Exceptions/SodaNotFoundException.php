<?php

declare(strict_types=1);

namespace Src\Catalog\Shared\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class SodaNotFoundException extends NotFoundException
{
    /** Build the exception for a soda that does not exist. */
    public static function create(): self
    {
        return new self('catalog.soda_not_found');
    }
}
