<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Application\UseCases;

use DateTimeImmutable;
use Src\Ordering\Orders\Application\Contracts\OrderableDishes;
use Src\Ordering\Orders\Application\Contracts\OrderDeadlines;
use Src\Ordering\Orders\Application\Contracts\PickupNumbers;
use Src\Ordering\Orders\Application\Contracts\PortionStock;
use Src\Ordering\Orders\Application\Contracts\SodaSchedule;
use Src\Ordering\Orders\Application\DTOs\PlacedOrder;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Ordering\Orders\Domain\Exceptions\DishNotOfSodaException;
use Src\Ordering\Orders\Domain\Exceptions\DishUnavailableException;
use Src\Ordering\Orders\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Ordering\Orders\Domain\Exceptions\PortionsSoldOutException;
use Src\Ordering\Orders\Domain\Exceptions\SodaClosedException;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\DishId;
use Src\Ordering\Orders\Domain\ValueObjects\IdempotencyKey;
use Src\Ordering\Orders\Domain\ValueObjects\Quantity;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class PlaceOrder
{
    /** Receive the order repository, the catalog, the schedule, the portion stock, the pickup numbers, the order deadlines and the transaction ports. */
    public function __construct(
        private OrderRepository $orders,
        private OrderableDishes $catalog,
        private SodaSchedule $schedule,
        private PortionStock $portions,
        private PickupNumbers $pickupNumbers,
        private OrderDeadlines $deadlines,
        private TransactionRunner $transaction,
    ) {}

    /**
     * Place a pending order of an open soda, with the name, the price and the minutes each dish has in the catalog right now,
     * number it for the day and take its portions in the same transaction; if a dish falls short, nothing is saved.
     * The same request sent again, with the same key and the same lines, answers the order already placed
     * without taking portions again; the same key with other lines is refused.
     * The order gets checked again at its payment deadline.
     */
    public function execute(PlaceOrderCommand $command, DateTimeImmutable $now): PlacedOrder
    {
        $customerId = new CustomerId($command->customerId);
        $key = new IdempotencyKey($command->idempotencyKey, IdempotencyKey::fingerprintOf($command->lines));

        $previous = $this->orders->findByIdempotencyKey($customerId, $key->value);

        if ($previous !== null) {
            return $this->replay($previous, $key);
        }

        $sodaId = new SodaId($command->sodaId);

        if (! $this->schedule->isOpenAt($sodaId, $now)) {
            throw SodaClosedException::create();
        }

        $lines = $this->linesOf($sodaId, $command->lines);

        try {
            $order = $this->transaction->run(function () use ($customerId, $sodaId, $lines, $now, $key): Order {
                $pickupNumber = $this->pickupNumbers->nextFor($sodaId, $now);
                $order = Order::place($this->orders->nextId(), $sodaId, $customerId, $lines, $now, $pickupNumber, $key);

                if (! $this->portions->reserve($sodaId, $order->lines)) {
                    throw PortionsSoldOutException::create();
                }

                $this->orders->save($order);
                $this->deadlines->checkAt($order->id, $sodaId, $order->paymentDeadline());

                return $order;
            });
        } catch (IdempotencyKeyReusedException $exception) {
            // Another request with the same key won the race: the transaction rolled back and the winner is answered instead.
            $winner = $this->orders->findByIdempotencyKey($customerId, $key->value) ?? throw $exception;

            return $this->replay($winner, $key);
        }

        return new PlacedOrder($order, true);
    }

    /** Answer the order a key already produced, as long as the request asks for the same lines. */
    private function replay(Order $previous, IdempotencyKey $key): PlacedOrder
    {
        if ($previous->idempotencyKey === null || ! $previous->idempotencyKey->repeats($key)) {
            throw IdempotencyKeyReusedException::create();
        }

        return new PlacedOrder($previous, false);
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

            $lines[] = new OrderLine(new DishId($dish->id), $dish->name, $dish->unitPrice, new Quantity($line['quantity']), $dish->preparationMinutes);
        }

        return $lines;
    }
}
