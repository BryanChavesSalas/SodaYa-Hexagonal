<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class OrderNotFoundException extends NotFoundException
{
    /** Build the exception for an order the customer does not have: one of someone else looks the same as one that does not exist. */
    public static function create(): self
    {
        return new self('payments.order_not_found');
    }
}
