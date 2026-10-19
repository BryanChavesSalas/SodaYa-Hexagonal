<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\ValueObjects;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
}
