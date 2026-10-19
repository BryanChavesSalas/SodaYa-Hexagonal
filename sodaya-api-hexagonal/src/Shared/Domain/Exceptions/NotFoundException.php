<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

abstract class NotFoundException extends DomainException
{
    /** Stable identifier of what is missing; the API publishes it as the type of the problem, or the generic one when there is none. */
    public function type(): ?string
    {
        return null;
    }
}
