<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Queue;

use Src\Ordering\Orders\Application\Contracts\CustomerNotices;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Infrastructure\Jobs\NotifyCustomerJob;

final readonly class QueuedCustomerNotices implements CustomerNotices
{
    /** Leave the notice that the order is ready in the queue. */
    public function orderReady(Order $order): void
    {
        NotifyCustomerJob::dispatch($order->customerId->value, $order->id->value, NotifyCustomerJob::ORDER_READY);
    }

    /** Leave the notice of the cancellation in the queue, with its origin as the reason. */
    public function orderCancelled(Order $order): void
    {
        NotifyCustomerJob::dispatch($order->customerId->value, $order->id->value, NotifyCustomerJob::ORDER_CANCELLED, $order->cancellationOrigin?->value);
    }
}
