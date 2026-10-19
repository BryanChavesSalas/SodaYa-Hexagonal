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
        NotifyCustomerJob::dispatch($order->id->value, $order->sodaId->value, NotifyCustomerJob::ORDER_READY);
    }

    /** Leave the notice of the cancellation in the queue; its origin and its refund are read from the order when the job runs. */
    public function orderCancelled(Order $order): void
    {
        NotifyCustomerJob::dispatch($order->id->value, $order->sodaId->value, NotifyCustomerJob::ORDER_CANCELLED);
    }
}
