<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class OrderNotPaidException extends BusinessRuleException
{
    /** Build the exception for an order the kitchen tries to confirm before its payment is approved. */
    public static function create(): self
    {
        return new self('ordering.order_not_paid');
    }

    /** The order cannot be confirmed without an approved payment. */
    public function rule(): string
    {
        return 'pedido-sin-pago';
    }
}
