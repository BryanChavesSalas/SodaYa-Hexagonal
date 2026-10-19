<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\OrderDeadlines;
use Src\Ordering\Orders\Application\Contracts\PortionStock;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Ordering\Orders\Domain\Exceptions\PortionsSoldOutException;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\DishId;
use Src\Ordering\Orders\Domain\ValueObjects\Quantity;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class PlaceOrder
{
    /** Receive the order repository, the portion stock, the order deadlines and the transaction ports. */
    public function __construct(
        private OrderRepository $orders,
        private PortionStock $portions,
        private OrderDeadlines $deadlines,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Place a pending order and take its portions in the same transaction; if a dish falls short, nothing is saved.
     * The order gets checked again at its payment deadline.
     */
    public function execute(PlaceOrderCommand $command, DateTimeImmutable $now): Order
    {
        return $this->transaction->run(function () use ($command, $now): Order {
            $sodaId = new SodaId($command->sodaId);
            $order = Order::place(
                $this->orders->nextId(),
                $sodaId,
                new CustomerId($command->customerId),
                array_map(fn (array $line): OrderLine => new OrderLine(
                    new DishId($line['dish_id']),
                    $line['dish_name'],
                    $line['unit_price'],
                    new Quantity($line['quantity']),
                ), $command->lines),
                $now,
            );

            if (! $this->portions->reserve($sodaId, $order->lines)) {
                throw PortionsSoldOutException::create();
            }

            $this->orders->save($order);
            $this->deadlines->checkAt($order->id, $sodaId, $order->paymentDeadline());

            return $order;
        });
    }
}
