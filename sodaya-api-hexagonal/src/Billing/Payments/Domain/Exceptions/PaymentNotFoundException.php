<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class PaymentNotFoundException extends NotFoundException
{
    /** Build the exception for an order nobody has tried to pay yet. */
    public static function create(): self
    {
        return new self('payments.payment_not_found');
    }
}
