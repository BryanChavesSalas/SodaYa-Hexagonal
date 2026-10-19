<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class OrderEmptyException extends BusinessRuleException
{
    /** Build the exception for an order placed without lines. */
    public static function create(): self
    {
        return new self('ordering.order_empty');
    }

    /** An order needs at least one line. */
    public function rule(): string
    {
        return 'pedido-vacio';
    }
}
