<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class PortionsSoldOutException extends BusinessRuleException
{
    /** Build the exception for an order that asks for more portions than the soda has left. */
    public static function create(): self
    {
        return new self('ordering.portions_sold_out');
    }

    /** A dish of the order has fewer portions left than the order asks for. */
    public function rule(): string
    {
        return 'porciones-agotadas';
    }
}
