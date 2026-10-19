<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Application;

use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Application\UseCases\PlaceOrder;
use Src\Ordering\Orders\Domain\Exceptions\OrderEmptyException;
use Src\Ordering\Orders\Domain\Exceptions\PortionsSoldOutException;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Tests\Support\Ordering\FakeOrderDeadlines;
use Tests\Support\Ordering\FakePortionStock;
use Tests\Support\Ordering\InMemoryOrderRepository;
use Tests\Support\Ordering\Orders;
use Tests\Support\Shared\RecordingTransactionRunner;

final class PlaceOrderTest extends TestCase
{
    private InMemoryOrderRepository $orders;

    private FakePortionStock $portions;

    private FakeOrderDeadlines $deadlines;

    private RecordingTransactionRunner $transaction;

    private PlaceOrder $placeOrder;

    /** Wire the use case to in-memory adapters; the soda has 3 casados and 10 frescos left. */
    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository;
        $this->portions = new FakePortionStock([Orders::CASADO => 3, Orders::FRESCO => 10]);
        $this->deadlines = new FakeOrderDeadlines;
        $this->transaction = new RecordingTransactionRunner;
        $this->placeOrder = new PlaceOrder($this->orders, $this->portions, $this->deadlines, $this->transaction);
    }

    /** The order is saved pending and its portions are taken, in one transaction. */
    public function test_places_a_pending_order_and_takes_its_portions(): void
    {
        $order = $this->placeOrder->execute($this->command([Orders::CASADO => 2, Orders::FRESCO => 1]), Orders::at('12:00'));

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(6_600, $order->total());
        $this->assertNotNull($this->orders->find($order->id, $order->sodaId));
        $this->assertSame([Orders::CASADO => 2, Orders::FRESCO => 1], $this->portions->reserved);
        $this->assertSame(1, $this->transaction->committed());
    }

    /** A new order is checked again ten minutes after it was placed. */
    public function test_order_is_checked_at_its_payment_deadline(): void
    {
        $order = $this->placeOrder->execute($this->command([Orders::CASADO => 1]), Orders::at('12:00'));

        $this->assertEquals([$order->id->value => Orders::at('12:10')], $this->deadlines->checks);
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

    /** An order the domain rejects, like an empty one, never reaches the stock. */
    public function test_invalid_order_reserves_nothing(): void
    {
        $this->expectException(OrderEmptyException::class);

        try {
            $this->placeOrder->execute(new PlaceOrderCommand(Orders::CUSTOMER_ID, Orders::SODA_ID, []), Orders::at('12:00'));
        } finally {
            $this->assertSame([], $this->portions->reserved);
        }
    }

    /**
     * Build the command for the quantities of each dish, at the prices of the scenario.
     *
     * @param  array<string, int>  $quantities
     */
    private function command(array $quantities): PlaceOrderCommand
    {
        $lines = [];

        foreach ($quantities as $dishId => $quantity) {
            $casado = $dishId === Orders::CASADO;
            $lines[] = [
                'dish_id' => $dishId,
                'dish_name' => $casado ? 'Casado de pollo' : 'Fresco de cas',
                'unit_price' => $casado ? 2_800 : 1_000,
                'quantity' => $quantity,
            ];
        }

        return new PlaceOrderCommand(Orders::CUSTOMER_ID, Orders::SODA_ID, $lines);
    }
}
