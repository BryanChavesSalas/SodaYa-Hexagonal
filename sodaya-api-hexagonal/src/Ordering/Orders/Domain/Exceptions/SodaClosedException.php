<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class SodaClosedException extends BusinessRuleException
{
    /** Build the exception for an order placed while the soda is not serving. */
    public static function create(): self
    {
        return new self('ordering.soda_closed');
    }

    /** A soda receives orders only while it is open. */
    public function rule(): string
    {
        return 'soda-cerrada';
    }
}
