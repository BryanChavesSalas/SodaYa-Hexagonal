<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Ordering\Orders\Infrastructure\Jobs\NotifyCustomerJob;
use Src\Ordering\Orders\Infrastructure\Jobs\RequestRefundJob;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class QueuedSideEffectsTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    /** Authenticate the kitchen of a soda and keep the jobs in a fake queue. */
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->actOnNewSoda(Role::Kitchen);
    }

    /** Cancelling a paid order answers at once and leaves the refund and the notice in the queue. */
    public function test_kitchen_cancellation_queues_the_refund_and_the_notice(): void
    {
        $order = OrderModel::factory()->confirmed()->create(['soda_id' => $this->soda->id]);
        OrderLineModel::factory()->for($order, 'order')->create(['unit_price' => 2_800, 'quantity' => 2]);

        $this->postJson("/api/v1/cocina/pedidos/{$order->id}/cancelacion")->assertOk();

        Queue::assertPushed(RequestRefundJob::class, fn (RequestRefundJob $job): bool => $job->orderId === $order->id && $job->amount === 5_600);
        Queue::assertPushed(NotifyCustomerJob::class, fn (NotifyCustomerJob $job): bool => $job->orderId === $order->id && $job->sodaId === $this->soda->id && $job->event === NotifyCustomerJob::ORDER_CANCELLED);
    }

    /** Marking an order ready answers at once and leaves the notice in the queue, with the order and its soda. */
    public function test_marking_an_order_ready_queues_the_notice(): void
    {
        $order = OrderModel::factory()->confirmed()->create(['soda_id' => $this->soda->id]);
        OrderLineModel::factory()->for($order, 'order')->create();

        $this->patchJson("/api/v1/cocina/pedidos/{$order->id}/estado", ['estado' => 'listo'])->assertOk();

        Queue::assertPushed(NotifyCustomerJob::class, fn (NotifyCustomerJob $job): bool => $job->orderId === $order->id && $job->sodaId === $this->soda->id && $job->event === NotifyCustomerJob::ORDER_READY);
    }

    /** Confirming or delivering an order leaves no notice in the queue. */
    public function test_other_steps_queue_no_notice(): void
    {
        $order = OrderModel::factory()->paid()->create(['soda_id' => $this->soda->id]);
        OrderLineModel::factory()->for($order, 'order')->create();

        $this->patchJson("/api/v1/cocina/pedidos/{$order->id}/estado", ['estado' => 'confirmado'])->assertOk();
        Queue::assertNotPushed(NotifyCustomerJob::class);

        $this->patchJson("/api/v1/cocina/pedidos/{$order->id}/estado", ['estado' => 'listo'])->assertOk();
        $this->patchJson("/api/v1/cocina/pedidos/{$order->id}/estado", ['estado' => 'entregado'])->assertOk();
        Queue::assertPushed(NotifyCustomerJob::class, 1);
    }

    /** An unpaid order queues the notice but no refund. */
    public function test_unpaid_order_queues_no_refund(): void
    {
        $order = OrderModel::factory()->create(['soda_id' => $this->soda->id]);
        OrderLineModel::factory()->for($order, 'order')->create();

        $this->postJson("/api/v1/cocina/pedidos/{$order->id}/cancelacion")->assertOk();

        Queue::assertNotPushed(RequestRefundJob::class);
        Queue::assertPushed(NotifyCustomerJob::class);
    }

    /** Until the payment module exists, the refund job leaves a warning with the order and the amount. */
    public function test_refund_job_writes_the_pending_refund_to_the_log(): void
    {
        Log::shouldReceive('warning')->once()->with('Refund pending for a cancelled order.', [
            'order_id' => 'pedido', 'soda_id' => 'soda', 'amount' => 5_600,
        ]);

        new RequestRefundJob('pedido', 'soda', 5_600)->handle();
    }
}
