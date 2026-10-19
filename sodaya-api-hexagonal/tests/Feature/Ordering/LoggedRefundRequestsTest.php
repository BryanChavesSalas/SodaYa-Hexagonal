<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Facades\Log;
use Src\Ordering\Orders\Infrastructure\Payments\LoggedRefundRequests;
use Tests\Support\Ordering\Orders;
use Tests\TestCase;

final class LoggedRefundRequestsTest extends TestCase
{
    /** Until the payment module exists, a refund request leaves a warning with the order, its soda and the amount. */
    public function test_refund_request_is_written_to_the_log(): void
    {
        $order = Orders::paid();

        Log::shouldReceive('warning')->once()->with('Refund pending for a cancelled order.', [
            'order_id' => Orders::ORDER_ID,
            'soda_id' => Orders::SODA_ID,
            'amount' => $order->total(),
        ]);

        new LoggedRefundRequests()->request($order);
    }
}
