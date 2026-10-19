<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Payments;

use Illuminate\Support\Facades\Log;
use Src\Ordering\Orders\Application\Contracts\RefundRequests;
use Src\Ordering\Orders\Domain\Entities\Order;

/** Provisional adapter: it leaves the refund in the log until the payment module charges and refunds for real. */
final class LoggedRefundRequests implements RefundRequests
{
    /** Write the pending refund to the log. */
    public function request(Order $order): void
    {
        Log::warning('Refund pending for a cancelled order.', [
            'order_id' => $order->id->value,
            'soda_id' => $order->sodaId->value,
            'amount' => $order->total(),
        ]);
    }
}
