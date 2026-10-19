<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Application;

use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Application\DTOs\ApproveOrderPaymentCommand;
use Src\Ordering\Orders\Application\UseCases\ApproveOrderPayment;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Tests\Support\Ordering\FakeOrderDeadlines;
use Tests\Support\Ordering\InMemoryOrderRepository;
use Tests\Support\Ordering\Orders;
use Tests\Support\Shared\RecordingTransactionRunner;

final class ApproveOrderPaymentTest extends TestCase
{
    private InMemoryOrderRepository $orders;

    private FakeOrderDeadlines $deadlines;

    private ApproveOrderPayment $approvePayment;

    /** Wire the use case to in-memory adapters and store a pending order. */
    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository;
        $this->deadlines = new FakeOrderDeadlines;
        $this->approvePayment = new ApproveOrderPayment($this->orders, $this->deadlines, new RecordingTransactionRunner);

        $this->orders->save(Orders::pending());
    }

    /** Approving the payment stores it and checks the order again ten minutes later. */
    public function test_approval_starts_the_time_to_confirm(): void
    {
        $order = $this->approvePayment->execute($this->command(), Orders::at('12:04'));

        $this->assertTrue($order->isPaid());
        $this->assertTrue($this->orders->find($order->id, $order->sodaId)?->isPaid());
        $this->assertEquals([Orders::ORDER_ID => Orders::at('12:14')], $this->deadlines->checks);
    }

    /** A second approval of the same payment does not move the deadline. */
    public function test_repeated_approval_changes_nothing(): void
    {
        $this->approvePayment->execute($this->command(), Orders::at('12:04'));
        $saves = $this->orders->saves;

        $this->approvePayment->execute($this->command(), Orders::at('12:08'));

        $this->assertSame($saves, $this->orders->saves);
        $this->assertEquals([Orders::ORDER_ID => Orders::at('12:14')], $this->deadlines->checks);
    }

    /** An order of another soda is not found. */
    public function test_order_of_another_soda_is_not_found(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->approvePayment->execute(new ApproveOrderPaymentCommand(Orders::ORDER_ID, '0192f0c4-0000-7000-8000-00000000b002'), Orders::at('12:04'));
    }

    /** The command for the order of the scenario. */
    private function command(): ApproveOrderPaymentCommand
    {
        return new ApproveOrderPaymentCommand(Orders::ORDER_ID, Orders::SODA_ID);
    }
}
