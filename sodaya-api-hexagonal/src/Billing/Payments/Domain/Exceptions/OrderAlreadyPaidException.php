<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\BusinessRuleException;

final class OrderAlreadyPaidException extends BusinessRuleException
{
    /** Build the exception for an order that already has an approved payment. */
    public static function create(): self
    {
        return new self('payments.order_already_paid');
    }

    /** An order is paid once. */
    public function rule(): string
    {
        return 'pedido-ya-pagado';
    }
}
