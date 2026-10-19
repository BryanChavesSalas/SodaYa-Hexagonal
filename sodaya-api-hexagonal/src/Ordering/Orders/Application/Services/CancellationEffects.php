<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\Services;

use Src\Ordering\Orders\Application\Contracts\CustomerNotices;
use Src\Ordering\Orders\Application\Contracts\PortionStock;
use Src\Ordering\Orders\Application\Contracts\RefundRequests;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;

/** What follows the cancellation of an order, whoever cancels it: the kitchen, the customer or a deadline. */
final readonly class CancellationEffects
{
    /** Receive the ports a cancellation touches. */
    public function __construct(
        private OrderRepository $orders,
        private PortionStock $portions,
        private RefundRequests $refunds,
        private CustomerNotices $notices,
    ) {}

    /** Inside the transaction that cancelled the order: store it and give its portions back. */
    public function persist(Order $order): void
    {
        $this->orders->save($order);
        $this->portions->release($order->sodaId, $order->lines);
    }

    /** Once the cancellation is committed: refund a paid order and tell the customer, unless the customer cancelled. */
    public function announce(Order $order): void
    {
        if ($order->isPaid()) {
            $this->refunds->request($order);
        }

        if ($order->cancellationOrigin !== CancellationOrigin::Customer) {
            $this->notices->orderCancelled($order);
        }
    }
}
