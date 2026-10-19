<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use DateTimeImmutable;
use Illuminate\Support\Facades\Queue;
use Src\Billing\Payments\Application\Contracts\OrderPayments;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Infrastructure\Ordering\OrderingOrderPayments;
use Src\Ordering\Orders\Infrastructure\Jobs\ExpireOrderJob;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class OrderingOrderPaymentsTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    /** Marking the order paid goes through the orders context: the order records the approval and its confirmation deadline is scheduled. */
    public function test_marks_the_order_paid_through_the_orders_context(): void
    {
        Queue::fake();
        $order = OrderModel::factory()->create();
        $this->scopeQueriesTo($order->soda_id);
        $at = new DateTimeImmutable('2026-10-05 12:00:00', new \DateTimeZone('America/Costa_Rica'));

        $this->app->make(OrderPayments::class)->markPaid(new OrderId($order->id), new SodaId($order->soda_id), $at);

        $order->refresh();
        $this->assertSame('approved', $order->payment_status);
        $this->assertEquals($at, $order->paid_at?->toDateTimeImmutable());
        Queue::assertPushed(ExpireOrderJob::class);
    }

    /** The container hands out the adapter of the orders context for the port. */
    public function test_the_container_resolves_the_orders_adapter(): void
    {
        $this->assertInstanceOf(OrderingOrderPayments::class, $this->app->make(OrderPayments::class));
    }
}
