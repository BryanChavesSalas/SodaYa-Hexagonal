<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Application;

use LogicException;
use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Application\DTOs\AdvanceOrderCommand;
use Src\Ordering\Orders\Application\UseCases\AdvanceOrder;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotPaidException;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Ordering\FakeLoyaltyRewards;
use Tests\Support\Ordering\InMemoryOrderRepository;
use Tests\Support\Ordering\Orders;
use Tests\Support\Shared\RecordingTransactionRunner;

final class AdvanceOrderTest extends TestCase
{
    private InMemoryOrderRepository $orders;

    private RecordingTransactionRunner $transaction;

    private FakeLoyaltyRewards $rewards;

    private AdvanceOrder $advanceOrder;

    /** Wire the use case to in-memory adapters. */
    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository;
        $this->transaction = new RecordingTransactionRunner;
        $this->rewards = new FakeLoyaltyRewards;
        $this->advanceOrder = new AdvanceOrder($this->orders, $this->rewards, $this->transaction);
    }

    /** A paid order is confirmed and stored in one transaction. */
    public function test_paid_order_is_confirmed_and_stored(): void
    {
        $this->orders->save(Orders::paid());

        $order = $this->advanceOrder->execute($this->command('confirmed'), Orders::at('12:01'));

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(OrderStatus::Confirmed, $this->stored()->status);
        $this->assertSame(1, $this->transaction->committed());
    }

    /** Only the delivery rewards the customer, once it is stored. */
    public function test_delivery_rewards_the_customer(): void
    {
        $this->orders->save(Orders::paid());

        $this->advanceOrder->execute($this->command('confirmed'), Orders::at('12:01'));
        $this->advanceOrder->execute($this->command('ready'), Orders::at('12:10'));
        $this->assertSame([], $this->rewards->rewarded);

        $this->advanceOrder->execute($this->command('delivered'), Orders::at('12:15'));
        $this->assertSame([Orders::ORDER_ID], $this->rewards->rewarded);
    }

    /** An unpaid order is not confirmed and nothing is stored. */
    public function test_unpaid_order_is_not_confirmed(): void
    {
        $this->orders->save(Orders::pending());

        try {
            $this->advanceOrder->execute($this->command('confirmed'), Orders::at('12:01'));
            $this->fail('An unpaid order was confirmed.');
        } catch (OrderNotPaidException) {
            $this->assertSame(OrderStatus::Pending, $this->stored()->status);
            $this->assertSame(1, $this->transaction->rolledBack());
        }
    }

    /** An unknown order is not found. */
    public function test_unknown_order_is_not_found(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->advanceOrder->execute($this->command('confirmed'), Orders::at('12:01'));
    }

    /** Build the command for the order of the helper. */
    private function command(string $status): AdvanceOrderCommand
    {
        return new AdvanceOrderCommand(Orders::ORDER_ID, Orders::SODA_ID, $status);
    }

    /** Read the stored order. */
    private function stored(): Order
    {
        return $this->orders->find(new OrderId(Orders::ORDER_ID), new SodaId(Orders::SODA_ID)) ?? throw new LogicException('Missing order.');
    }
}
