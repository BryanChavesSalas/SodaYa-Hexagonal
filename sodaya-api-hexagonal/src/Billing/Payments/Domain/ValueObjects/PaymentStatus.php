<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /** Whether a payment in this status counts as the live payment of its order: only a rejected one leaves room for another attempt. */
    public function isLive(): bool
    {
        return $this !== self::Rejected;
    }
}
