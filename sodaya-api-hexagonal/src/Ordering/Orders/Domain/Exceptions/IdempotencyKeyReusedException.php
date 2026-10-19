<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class IdempotencyKeyReusedException extends BusinessRuleException
{
    /** Build the exception for a key the customer already used with a different order. */
    public static function create(): self
    {
        return new self('ordering.idempotency_key_reused');
    }

    /** A key names one request; sending it again with other content is a conflict. */
    public function rule(): string
    {
        return 'conflicto';
    }
}
