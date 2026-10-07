<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class ClosureAlreadyExistsException extends DomainException
{
    public static function create(): self
    {
        return new self('closure_already_exists');
    }
}
