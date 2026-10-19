<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

abstract class ServiceUnavailableException extends DomainException
{
    /** Seconds the client should wait before trying again; the API sends them in the Retry-After header. */
    public function retryAfterSeconds(): int
    {
        return 30;
    }
}
