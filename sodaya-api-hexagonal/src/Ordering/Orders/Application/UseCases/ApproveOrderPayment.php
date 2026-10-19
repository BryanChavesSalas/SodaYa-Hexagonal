<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\OrderDeadlines;
use Src\Ordering\Orders\Application\DTOs\ApproveOrderPaymentCommand;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ApproveOrderPayment
{
    /** Receive the order repository, the order deadlines and the transaction ports. */
    public function __construct(
        private OrderRepository $orders,
        private OrderDeadlines $deadlines,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Record that the payment of the order was approved and start the time the kitchen has to confirm it.
     * The payment module calls it when the payment gateway approves the charge; a repeated approval changes nothing.
     */
    public function execute(ApproveOrderPaymentCommand $command, DateTimeImmutable $now): Order
    {
        return $this->transaction->run(function () use ($command, $now): Order {
            $order = $this->orders->findForUpdate(new OrderId($command->orderId), new SodaId($command->sodaId))
                ?? throw OrderNotFoundException::create();

            if ($order->approvePayment($now)) {
                $this->orders->save($order);
                $this->deadlines->checkAt($order->id, $order->sodaId, $now->modify('+'.Order::MINUTES_TO_CONFIRM.' minutes'));
            }

            return $order;
        });
    }
}
