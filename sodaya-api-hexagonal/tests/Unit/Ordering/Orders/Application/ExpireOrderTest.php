<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Application;

use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Application\DTOs\ExpireOrderCommand;
use Src\Ordering\Orders\Application\Services\CancellationEffects;
use Src\Ordering\Orders\Application\UseCases\ExpireOrder;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Tests\Support\Ordering\FakeCustomerNotices;
use Tests\Support\Ordering\FakePortionStock;
use Tests\Support\Ordering\FakeRefundRequests;
use Tests\Support\Ordering\InMemoryOrderRepository;
use Tests\Support\Ordering\Orders;
use Tests\Support\Shared\RecordingTransactionRunner;

final class ExpireOrderTest extends TestCase
{
    private InMemoryOrderRepository $orders;

    private FakePortionStock $portions;

    private FakeCustomerNotices $notices;

    private ExpireOrder $expireOrder;

    /** Wire the use case to in-memory adapters. */
    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository;
        $this->portions = new FakePortionStock;
        $this->notices = new FakeCustomerNotices;
        $this->expireOrder = new ExpireOrder(
            $this->orders,
            new CancellationEffects($this->orders, $this->portions, new FakeRefundRequests, $this->notices),
            new RecordingTransactionRunner,
        );
    }

    /** At its deadline, an unpaid order is cancelled for lack of payment, gives its portions back and the customer hears about it. */
    public function test_unpaid_order_is_cancelled_at_its_deadline(): void
    {
        $this->orders->save(Orders::pending());

        $this->assertTrue($this->expireOrder->execute($this->command(), Orders::at('12:10')));

        $order = $this->orders->find(Orders::pending()->id, Orders::pending()->sodaId);
        $this->assertSame(CancellationOrigin::PaymentTimeout, $order?->cancellationOrigin);
        $this->assertSame([Orders::CASADO => 2, Orders::FRESCO => 1], $this->portions->released);
        $this->assertSame([Orders::ORDER_ID], $this->notices->cancelled);
    }

    /** Before the deadline, after a payment, or for an order that does not exist, nothing happens. */
    public function test_order_on_time_is_left_alone(): void
    {
        $order = Orders::pending();
        $order->approvePayment(Orders::at('12:25'));
        $this->orders->save($order);

        $this->assertFalse($this->expireOrder->execute($this->command(), Orders::at('12:30')));
        $this->assertFalse($this->expireOrder->execute(new ExpireOrderCommand('0192f0c4-0000-7000-8000-00000000a999', Orders::SODA_ID), Orders::at('12:30')));
        $this->assertSame(1, $this->orders->saves);
        $this->assertSame([], $this->notices->cancelled);
    }

    /** The command for the order of the scenario. */
    private function command(): ExpireOrderCommand
    {
        return new ExpireOrderCommand(Orders::ORDER_ID, Orders::SODA_ID);
    }
}
