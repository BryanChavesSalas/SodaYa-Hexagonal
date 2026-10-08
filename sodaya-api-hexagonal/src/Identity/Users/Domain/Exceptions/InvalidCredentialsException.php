<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class InvalidCredentialsException extends DomainException
{
    /** Build the exception for invalid authentication credentials. */
    public static function create(): self
    {
        return new self('identity.invalid_credentials');
    }
}
