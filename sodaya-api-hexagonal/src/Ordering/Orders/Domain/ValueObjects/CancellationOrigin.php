<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\ValueObjects;

enum CancellationOrigin: string
{
    case Customer = 'customer';
    case Kitchen = 'kitchen';
    case PaymentTimeout = 'payment_timeout';
    case ConfirmationTimeout = 'confirmation_timeout';
}
