<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\OrderableDishes;
use Src\Ordering\Orders\Application\Contracts\OrderDeadlines;
use Src\Ordering\Orders\Application\Contracts\PortionStock;
use Src\Ordering\Orders\Application\Contracts\SodaSchedule;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Ordering\Orders\Domain\Exceptions\DishNotOfSodaException;
use Src\Ordering\Orders\Domain\Exceptions\DishUnavailableException;
use Src\Ordering\Orders\Domain\Exceptions\PortionsSoldOutException;
use Src\Ordering\Orders\Domain\Exceptions\SodaClosedException;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\DishId;
use Src\Ordering\Orders\Domain\ValueObjects\Quantity;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class PlaceOrder
{
    /** Receive the order repository, the catalog, the schedule, the portion stock, the order deadlines and the transaction ports. */
    public function __construct(
        private OrderRepository $orders,
        private OrderableDishes $catalog,
        private SodaSchedule $schedule,
        private PortionStock $portions,
        private OrderDeadlines $deadlines,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Place a pending order of an open soda, with the name and the price each dish has in the catalog right now,
     * and take its portions in the same transaction; if a dish falls short, nothing is saved.
     * The order gets checked again at its payment deadline.
     */
    public function execute(PlaceOrderCommand $command, DateTimeImmutable $now): Order
    {
        $sodaId = new SodaId($command->sodaId);

        if (! $this->schedule->isOpenAt($sodaId, $now)) {
            throw SodaClosedException::create();
        }

        $lines = $this->linesOf($sodaId, $command->lines);

        return $this->transaction->run(function () use ($command, $sodaId, $lines, $now): Order {
            $order = Order::place($this->orders->nextId(), $sodaId, new CustomerId($command->customerId), $lines, $now);

            if (! $this->portions->reserve($sodaId, $order->lines)) {
                throw PortionsSoldOutException::create();
            }

            $this->orders->save($order);
            $this->deadlines->checkAt($order->id, $sodaId, $order->paymentDeadline());

            return $order;
        });
    }

    /**
     * Build the lines from the catalog: a dish the soda does not have, or took off the menu, stops the order.
     *
     * @param  list<array{dish_id: string, quantity: int}>  $requested
     * @return list<OrderLine>
     */
    private function linesOf(SodaId $sodaId, array $requested): array
    {
        $dishes = $this->catalog->ofSoda($sodaId, array_column($requested, 'dish_id'));
        $lines = [];

        foreach ($requested as $line) {
            $dish = $dishes[$line['dish_id']] ?? throw DishNotOfSodaException::create();

            if (! $dish->active) {
                throw DishUnavailableException::create();
            }

            $lines[] = new OrderLine(new DishId($dish->id), $dish->name, $dish->unitPrice, new Quantity($line['quantity']));
        }

        return $lines;
    }
}
