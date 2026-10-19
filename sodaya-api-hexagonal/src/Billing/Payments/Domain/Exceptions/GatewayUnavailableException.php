<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\ServiceUnavailableException;

final class GatewayUnavailableException extends ServiceUnavailableException
{
    /** Build the exception for a provider that did not answer: a timeout or a connection that failed. */
    public static function unreachable(): self
    {
        return new self('payments.gateway_unreachable');
    }

    /** Build the exception for a provider that answered with an error instead of a decision. */
    public static function status(int $status): self
    {
        return new self('payments.gateway_failed', ['status' => $status]);
    }
}
