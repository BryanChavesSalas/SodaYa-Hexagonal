<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Domain\ValueObjects;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** Status that follows in the kitchen flow; delivered and cancelled are final. */
    public function next(): ?self
    {
        return match ($this) {
            self::Pending => self::Confirmed,
            self::Confirmed => self::Ready,
            self::Ready => self::Delivered,
            self::Delivered, self::Cancelled => null,
        };
    }

    /** Tell whether an order in this status can still be cancelled. */
    public function isCancellable(): bool
    {
        return $this === self::Pending || $this === self::Confirmed;
    }

    /** Tell whether an order in this status can change to the target one. */
    public function canChangeTo(self $target): bool
    {
        return $target === $this->next() || ($target === self::Cancelled && $this->isCancellable());
    }
}
