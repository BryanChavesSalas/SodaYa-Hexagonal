<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Queue;

use Src\Ordering\Orders\Application\Contracts\RefundRequests;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Infrastructure\Jobs\RequestRefundJob;

final readonly class QueuedRefundRequests implements RefundRequests
{
    /** Leave the refund in the queue; the request answers without waiting for the payment gateway. */
    public function request(Order $order): void
    {
        RequestRefundJob::dispatch($order->id->value, $order->sodaId->value, $order->total());
    }
}
