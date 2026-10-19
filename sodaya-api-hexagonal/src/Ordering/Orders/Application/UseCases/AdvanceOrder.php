<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\CustomerNotices;
use Src\Ordering\Orders\Application\Contracts\LoyaltyRewards;
use Src\Ordering\Orders\Application\DTOs\AdvanceOrderCommand;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class AdvanceOrder
{
    /** Receive the order repository, the loyalty rewards, the customer notices and the transaction ports. */
    public function __construct(
        private OrderRepository $orders,
        private LoyaltyRewards $rewards,
        private CustomerNotices $notices,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Move an order of the soda one step forward; the domain refuses a jump, a step back or an unpaid confirmation.
     * Once the step is committed, a ready order is announced to its customer and a delivered one rewards them.
     */
    public function execute(AdvanceOrderCommand $command, DateTimeImmutable $now): Order
    {
        $order = $this->transaction->run(function () use ($command, $now): Order {
            $order = $this->orders->findForUpdate(new OrderId($command->orderId), new SodaId($command->sodaId))
                ?? throw OrderNotFoundException::create();

            $order->advanceTo(OrderStatus::from($command->status), $now);
            $this->orders->save($order);

            return $order;
        });

        if ($order->status === OrderStatus::Ready) {
            $this->notices->orderReady($order);
        }

        if ($order->status === OrderStatus::Delivered) {
            $this->rewards->orderDelivered($order);
        }

        return $order;
    }
}
