<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Loyalty\Points\Infrastructure\Jobs\AwardPurchasePointsJob;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class AwardPointsOnDeliveryTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private OrderModel $order;

    /** The kitchen of a soda has a ready order of two casados and a fresco: ₡6600. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda(Role::Kitchen);
        $this->order = OrderModel::factory()->ready()->create(['soda_id' => $this->soda->id]);
        OrderLineModel::factory()->for($this->order, 'order')->create(['unit_price' => 2_800, 'quantity' => 2]);
        OrderLineModel::factory()->for($this->order, 'order')->create(['unit_price' => 1_000, 'quantity' => 1]);
    }

    /** Delivering the order answers at once and leaves the points in the queue. */
    public function test_delivery_queues_the_points(): void
    {
        Queue::fake();

        $this->deliver()->assertOk();

        Queue::assertPushed(AwardPurchasePointsJob::class, fn (AwardPurchasePointsJob $job): bool => $job->customerId === $this->order->customer_id
            && $job->orderId === $this->order->id
            && $job->amount === 6_600);
    }

    /** The job credits 6 points for ₡6600, and running it again credits nothing more. */
    public function test_points_are_credited_once(): void
    {
        $this->deliver()->assertOk();
        app()->call([new AwardPurchasePointsJob($this->order->customer_id, $this->order->id, 6_600), 'handle']);

        $movements = DB::table('point_movements')->where('customer_id', $this->order->customer_id)->get();
        $this->assertCount(1, $movements);
        $this->assertSame('purchase', $movements[0]->reason);
        $this->assertSame(6, $movements[0]->points);
        $this->assertSame($this->order->id, $movements[0]->order_id);
    }

    /** Deliver the order through the API. */
    private function deliver(): TestResponse
    {
        return $this->patchJson("/api/v1/cocina/pedidos/{$this->order->id}/estado", ['estado' => 'entregado']);
    }
}
