<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class ClosureNotFoundException extends NotFoundException
{
    /** Build the exception for a closure missing from the soda. */
    public static function create(): self
    {
        return new self('sodas.closure_not_found');
    }
}
