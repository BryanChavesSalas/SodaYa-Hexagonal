<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class OrderNotFoundException extends NotFoundException
{
    /** Build the exception for an order missing from the soda, or from the orders of the customer. */
    public static function create(): self
    {
        return new self('ordering.order_not_found');
    }

    /** The order is missing, whether it never existed or belongs to someone else. */
    public function type(): string
    {
        return 'pedido-no-encontrado';
    }
}
