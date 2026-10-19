<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Application;

use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Application\DTOs\OrderableDish;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Application\UseCases\PlaceOrder;
use Src\Ordering\Orders\Domain\Exceptions\DishNotOfSodaException;
use Src\Ordering\Orders\Domain\Exceptions\DishUnavailableException;
use Src\Ordering\Orders\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Ordering\Orders\Domain\Exceptions\OrderEmptyException;
use Src\Ordering\Orders\Domain\Exceptions\PortionsSoldOutException;
use Src\Ordering\Orders\Domain\Exceptions\QuantityOutOfRangeException;
use Src\Ordering\Orders\Domain\Exceptions\SodaClosedException;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Tests\Support\Ordering\FakeOrderableDishes;
use Tests\Support\Ordering\FakeOrderDeadlines;
use Tests\Support\Ordering\FakePortionStock;
use Tests\Support\Ordering\FakeSodaSchedule;
use Tests\Support\Ordering\InMemoryOrderRepository;
use Tests\Support\Ordering\LateIdempotencyLookup;
use Tests\Support\Ordering\Orders;
use Tests\Support\Shared\RecordingTransactionRunner;

final class PlaceOrderTest extends TestCase
{
    private const string CHIFRIJO = '0192f0c4-0000-7000-8000-00000000d003';

    private const string FOREIGN_DISH = '0192f0c4-0000-7000-8000-00000000d099';

    private InMemoryOrderRepository $orders;

    private FakePortionStock $portions;

    private FakeSodaSchedule $schedule;

    private FakeOrderDeadlines $deadlines;

    private RecordingTransactionRunner $transaction;

    private PlaceOrder $placeOrder;

    /** Wire the use case to in-memory adapters: an open soda with casados, frescos and a chifrijo taken off the menu. */
    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository;
        $this->portions = new FakePortionStock([Orders::CASADO => 3, Orders::FRESCO => 10]);
        $this->schedule = new FakeSodaSchedule;
        $this->deadlines = new FakeOrderDeadlines;
        $this->transaction = new RecordingTransactionRunner;
        $this->placeOrder = new PlaceOrder(
            $this->orders,
            new FakeOrderableDishes([
                Orders::CASADO => new OrderableDish(Orders::CASADO, 'Casado de pollo', 2_800, true),
                Orders::FRESCO => new OrderableDish(Orders::FRESCO, 'Fresco de cas', 1_000, true),
                self::CHIFRIJO => new OrderableDish(self::CHIFRIJO, 'Chifrijo', 3_000, false),
            ]),
            $this->schedule,
            $this->portions,
            $this->deadlines,
            $this->transaction,
        );
    }

    /** The order is saved pending with the names and prices of the catalog, and its portions are taken, in one transaction. */
    public function test_places_a_pending_order_with_the_catalog_prices_and_takes_its_portions(): void
    {
        $placed = $this->placeOrder->execute($this->command([Orders::CASADO => 2, Orders::FRESCO => 1]), Orders::at('12:00'));

        $this->assertTrue($placed->created);
        $this->assertSame(OrderStatus::Pending, $placed->order->status);
        $this->assertSame('Casado de pollo', $placed->order->lines[0]->dishName);
        $this->assertSame(2_800, $placed->order->lines[0]->unitPrice);
        $this->assertSame(6_600, $placed->order->total());
        $this->assertSame('toque-1', $placed->order->idempotencyKey?->value);
        $this->assertNotNull($this->orders->find($placed->order->id, $placed->order->sodaId));
        $this->assertSame([Orders::CASADO => 2, Orders::FRESCO => 1], $this->portions->reserved);
        $this->assertSame(1, $this->transaction->committed());
    }

    /** The same request sent again answers the order already placed, without a second order or more portions taken. */
    public function test_repeated_request_answers_the_same_order_once(): void
    {
        $first = $this->placeOrder->execute($this->command([Orders::CASADO => 2]), Orders::at('12:00'));
        $again = $this->placeOrder->execute($this->command([Orders::CASADO => 2]), Orders::at('12:01'));

        $this->assertFalse($again->created);
        $this->assertTrue($first->order->id->equals($again->order->id));
        $this->assertSame(1, $this->orders->saves);
        $this->assertSame([Orders::CASADO => 2], $this->portions->reserved);
        $this->assertSame(1, $this->transaction->runs());
    }

    /** The same key with other lines is a conflict, and nothing new is saved. */
    public function test_same_key_with_other_lines_is_refused(): void
    {
        $this->placeOrder->execute($this->command([Orders::CASADO => 2]), Orders::at('12:00'));

        try {
            $this->placeOrder->execute($this->command([Orders::CASADO => 1]), Orders::at('12:01'));
            $this->fail('A key was reused with other content.');
        } catch (IdempotencyKeyReusedException $exception) {
            $this->assertSame('conflicto', $exception->rule());
            $this->assertSame(1, $this->orders->saves);
            $this->assertSame([Orders::CASADO => 2], $this->portions->reserved);
        }
    }

    /** Another key places another order, even with the same lines. */
    public function test_another_key_places_another_order(): void
    {
        $first = $this->placeOrder->execute($this->command([Orders::CASADO => 1], 'toque-1'), Orders::at('12:00'));
        $second = $this->placeOrder->execute($this->command([Orders::CASADO => 1], 'toque-2'), Orders::at('12:01'));

        $this->assertTrue($second->created);
        $this->assertFalse($first->order->id->equals($second->order->id));
        $this->assertSame([Orders::CASADO => 2], $this->portions->reserved);
    }

    /**
     * Two identical requests at the same instant: the second does not see the first before saving, the database
     * refuses its order, and it answers the order of the first one instead of a conflict.
     */
    public function test_request_that_loses_the_race_answers_the_winner(): void
    {
        $winner = $this->placeOrder->execute($this->command([Orders::CASADO => 1]), Orders::at('12:00'));
        $racing = new PlaceOrder(
            new LateIdempotencyLookup($this->orders),
            new FakeOrderableDishes([Orders::CASADO => new OrderableDish(Orders::CASADO, 'Casado de pollo', 2_800, true)]),
            $this->schedule,
            $this->portions,
            $this->deadlines,
            $this->transaction,
        );

        $loser = $racing->execute($this->command([Orders::CASADO => 1]), Orders::at('12:00'));

        $this->assertFalse($loser->created);
        $this->assertTrue($winner->order->id->equals($loser->order->id));
        $this->assertSame(1, $this->orders->saves);
        $this->assertSame(1, $this->transaction->rolledBack());
    }

    /** A blank key is invalid data. */
    public function test_blank_key_is_refused(): void
    {
        $this->expectException(InvalidValueException::class);

        $this->placeOrder->execute($this->command([Orders::CASADO => 1], ' '), Orders::at('12:00'));
    }

    /** A new order is checked again ten minutes after it was placed. */
    public function test_order_is_checked_at_its_payment_deadline(): void
    {
        $placed = $this->placeOrder->execute($this->command([Orders::CASADO => 1]), Orders::at('12:00'));

        $this->assertEquals([$placed->order->id->value => Orders::at('12:10')], $this->deadlines->checks);
    }

    /** When a dish falls short, the order is not saved and the transaction rolls back. */
    public function test_short_dish_saves_nothing(): void
    {
        try {
            $this->placeOrder->execute($this->command([Orders::CASADO => 4]), Orders::at('12:00'));
            $this->fail('An order took more portions than the soda had.');
        } catch (PortionsSoldOutException $exception) {
            $this->assertSame('porciones-agotadas', $exception->rule());
            $this->assertSame(0, $this->orders->saves);
            $this->assertSame([], $this->deadlines->checks);
            $this->assertSame([], $this->portions->reserved);
            $this->assertSame(1, $this->transaction->rolledBack());
        }
    }

    /** A closed soda refuses the order before touching the catalog or the stock. */
    public function test_closed_soda_refuses_the_order(): void
    {
        $this->schedule->open = false;

        try {
            $this->placeOrder->execute($this->command([Orders::CASADO => 1]), Orders::at('12:00'));
            $this->fail('A closed soda took an order.');
        } catch (SodaClosedException $exception) {
            $this->assertSame('soda-cerrada', $exception->rule());
            $this->assertSame(0, $this->orders->saves);
            $this->assertSame(0, $this->transaction->runs());
        }
    }

    /** A dish the soda does not have stops the order. */
    public function test_dish_of_another_soda_is_refused(): void
    {
        $this->expectException(DishNotOfSodaException::class);

        try {
            $this->placeOrder->execute($this->command([Orders::CASADO => 1, self::FOREIGN_DISH => 1]), Orders::at('12:00'));
        } finally {
            $this->assertSame([], $this->portions->reserved);
        }
    }

    /** A dish taken off the menu stops the order. */
    public function test_inactive_dish_is_refused(): void
    {
        $this->expectException(DishUnavailableException::class);

        try {
            $this->placeOrder->execute($this->command([self::CHIFRIJO => 1]), Orders::at('12:00'));
        } finally {
            $this->assertSame([], $this->portions->reserved);
        }
    }

    /** A quantity outside 1 to 20 is refused by its own rule. */
    public function test_invalid_quantity_is_refused(): void
    {
        $this->expectException(QuantityOutOfRangeException::class);

        $this->placeOrder->execute($this->command([Orders::CASADO => 21]), Orders::at('12:00'));
    }

    /** An order the domain rejects, like an empty one, never reaches the stock. */
    public function test_invalid_order_reserves_nothing(): void
    {
        $this->expectException(OrderEmptyException::class);

        try {
            $this->placeOrder->execute(new PlaceOrderCommand(Orders::CUSTOMER_ID, Orders::SODA_ID, [], 'toque-1'), Orders::at('12:00'));
        } finally {
            $this->assertSame([], $this->portions->reserved);
        }
    }

    /**
     * Build the command for the quantities of each dish, with the key of the request.
     *
     * @param  array<string, int>  $quantities
     */
    private function command(array $quantities, string $key = 'toque-1'): PlaceOrderCommand
    {
        $lines = [];

        foreach ($quantities as $dishId => $quantity) {
            $lines[] = ['dish_id' => $dishId, 'quantity' => $quantity];
        }

        return new PlaceOrderCommand(Orders::CUSTOMER_ID, Orders::SODA_ID, $lines, $key);
    }
}
