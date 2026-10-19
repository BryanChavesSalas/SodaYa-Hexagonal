<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class SodaNotFoundException extends NotFoundException
{
    /** Build the exception for an order addressed to a soda that does not exist. */
    public static function create(): self
    {
        return new self('ordering.soda_not_found');
    }
}
