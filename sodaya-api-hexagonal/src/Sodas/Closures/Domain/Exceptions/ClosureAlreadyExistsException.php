<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class ClosureAlreadyExistsException extends DomainException
{
    /** Build the exception for a date the soda already closed. */
    public static function create(): self
    {
        return new self('sodas.closure_already_exists');
    }
}
