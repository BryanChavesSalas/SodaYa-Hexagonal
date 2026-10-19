<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Application\DTOs\CancelOrderCommand;
use Src\Ordering\Orders\Application\Services\CancellationEffects;
use Src\Ordering\Orders\Application\UseCases\CancelOrder;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Src\Ordering\Orders\Domain\Exceptions\TransitionNotAllowedException;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Tests\Support\Ordering\FakeCustomerNotices;
use Tests\Support\Ordering\FakePortionStock;
use Tests\Support\Ordering\FakeRefundRequests;
use Tests\Support\Ordering\InMemoryOrderRepository;
use Tests\Support\Ordering\Orders;
use Tests\Support\Shared\RecordingTransactionRunner;

final class CancelOrderTest extends TestCase
{
    private InMemoryOrderRepository $orders;

    private FakePortionStock $portions;

    private FakeRefundRequests $refunds;

    private FakeCustomerNotices $notices;

    private RecordingTransactionRunner $transaction;

    private CancelOrder $cancelOrder;

    /** Wire the use case to in-memory adapters and store a pending order. */
    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository;
        $this->portions = new FakePortionStock;
        $this->refunds = new FakeRefundRequests;
        $this->notices = new FakeCustomerNotices;
        $this->transaction = new RecordingTransactionRunner;
        $this->cancelOrder = new CancelOrder(
            $this->orders,
            new CancellationEffects($this->orders, $this->portions, $this->refunds, $this->notices),
            $this->transaction,
        );

        $this->orders->save(Orders::pending());
    }

    /** Whatever its origin, a cancellation gives back the portions of every line in one transaction. */
    #[DataProvider('origins')]
    public function test_every_cancellation_returns_the_portions(CancellationOrigin $origin): void
    {
        $order = $this->cancelOrder->execute($this->command($origin), Orders::at('12:05'));

        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame($origin, $this->orders->find($order->id, $order->sodaId)?->cancellationOrigin);
        $this->assertSame([Orders::CASADO => 2, Orders::FRESCO => 1], $this->portions->released);
        $this->assertSame(1, $this->transaction->committed());
    }

    /**
     * Every origin of a cancellation.
     *
     * @return array<string, array{CancellationOrigin}>
     */
    public static function origins(): array
    {
        return array_combine(
            array_map(fn (CancellationOrigin $origin): string => $origin->value, CancellationOrigin::cases()),
            array_map(fn (CancellationOrigin $origin): array => [$origin], CancellationOrigin::cases()),
        );
    }

    /** Cancelling a cancelled order changes nothing and gives nothing back twice. */
    public function test_second_cancellation_has_no_effect(): void
    {
        $this->cancelOrder->execute($this->command(CancellationOrigin::Customer), Orders::at('12:05'));
        $savesAfterFirst = $this->orders->saves;

        $order = $this->cancelOrder->execute($this->command(CancellationOrigin::PaymentTimeout), Orders::at('12:10'));

        $this->assertSame(CancellationOrigin::Customer, $order->cancellationOrigin);
        $this->assertSame([Orders::CASADO => 2, Orders::FRESCO => 1], $this->portions->released);
        $this->assertSame($savesAfterFirst, $this->orders->saves);
    }

    /** A ready order is not cancelled and keeps its portions sold. */
    public function test_ready_order_is_not_cancelled(): void
    {
        $order = Orders::paid();
        $order->advanceTo(OrderStatus::Confirmed, Orders::at('12:01'));
        $order->advanceTo(OrderStatus::Ready, Orders::at('12:10'));
        $this->orders->save($order);

        try {
            $this->cancelOrder->execute($this->command(CancellationOrigin::Kitchen), Orders::at('12:11'));
            $this->fail('A ready order was cancelled.');
        } catch (TransitionNotAllowedException) {
            $this->assertSame([], $this->portions->released);
            $this->assertSame([], $this->refunds->requested);
            $this->assertSame(1, $this->transaction->rolledBack());
        }
    }

    /** Cancelling a paid order asks for its refund once, even if it is cancelled again. */
    public function test_paid_order_asks_for_its_refund_once(): void
    {
        $this->orders->save(Orders::paid());

        $this->cancelOrder->execute($this->command(CancellationOrigin::Kitchen), Orders::at('12:05'));
        $this->cancelOrder->execute($this->command(CancellationOrigin::Kitchen), Orders::at('12:06'));

        $this->assertSame([Orders::ORDER_ID], $this->refunds->requested);
    }

    /** The customer hears about a cancellation by anyone else, once. */
    public function test_customer_is_notified_once_when_someone_else_cancels(): void
    {
        $this->cancelOrder->execute($this->command(CancellationOrigin::Kitchen), Orders::at('12:05'));
        $this->cancelOrder->execute($this->command(CancellationOrigin::Kitchen), Orders::at('12:06'));

        $this->assertSame([Orders::ORDER_ID], $this->notices->cancelled);
    }

    /** A customer who cancels the order is not told what they just did. */
    public function test_customer_who_cancels_is_not_notified(): void
    {
        $this->cancelOrder->execute($this->command(CancellationOrigin::Customer), Orders::at('12:05'));

        $this->assertSame([], $this->notices->cancelled);
    }

    /** An order without an approved payment has nothing to give back. */
    public function test_unpaid_order_asks_for_no_refund(): void
    {
        $this->cancelOrder->execute($this->command(CancellationOrigin::Kitchen), Orders::at('12:05'));

        $this->assertSame([], $this->refunds->requested);
    }

    /** An order of another soda is not found. */
    public function test_order_of_another_soda_is_not_found(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->cancelOrder->execute(
            new CancelOrderCommand(Orders::ORDER_ID, '0192f0c4-0000-7000-8000-00000000b002', CancellationOrigin::Kitchen->value),
            Orders::at('12:05'),
        );
    }

    /** Build the command that cancels the stored order. */
    private function command(CancellationOrigin $origin): CancelOrderCommand
    {
        return new CancelOrderCommand(Orders::ORDER_ID, Orders::SODA_ID, $origin->value);
    }
}
