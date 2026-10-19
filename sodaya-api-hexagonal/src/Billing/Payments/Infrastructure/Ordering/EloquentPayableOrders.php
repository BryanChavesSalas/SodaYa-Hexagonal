<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Ordering;

use Src\Billing\Payments\Application\Contracts\PayableOrders;
use Src\Billing\Payments\Application\DTOs\PayableOrder;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;

final readonly class EloquentPayableOrders implements PayableOrders
{
    /**
     * Read the order of the customer from the orders table, as a read model: the amount is the sum of its frozen lines.
     * The policy of the table limits the search to the soda of the transaction.
     */
    public function find(OrderId $orderId, CustomerId $customerId): ?PayableOrder
    {
        $order = OrderModel::query()
            ->with('lines')
            ->where('customer_id', $customerId->value)
            ->find($orderId->value);

        if ($order === null) {
            return null;
        }

        return new PayableOrder(
            $order->id,
            $order->soda_id,
            $order->customer_id,
            (int) $order->lines->sum(fn (OrderLineModel $line): int => $line->unit_price * $line->quantity),
            $order->status === 'pending',
            $order->payment_status === 'approved',
        );
    }
}
