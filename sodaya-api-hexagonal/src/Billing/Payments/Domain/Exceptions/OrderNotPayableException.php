<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class OrderNotPayableException extends BusinessRuleException
{
    /** Build the exception for an order that is no longer pending. */
    public static function create(): self
    {
        return new self('payments.order_not_payable');
    }

    /** Only a pending order can be paid. */
    public function rule(): string
    {
        return 'pedido-no-pagable';
    }
}
