<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Application\UseCases\PlaceOrder;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Infrastructure\Jobs\ExpireOrderJob;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class UnpaidOrderExpiryTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private SodaModel $soda;

    private DishModel $dish;

    /** A soda with 5 casados, on a Monday at noon. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->soda = SodaModel::factory()->create();
        $this->dish = DishModel::factory()->create(['soda_id' => $this->soda->id, 'available_portions' => 5, 'price' => 2_800]);
    }

    /** Placing an order leaves in the queue a job that becomes available ten minutes later. */
    public function test_new_order_schedules_its_expiry_in_ten_minutes(): void
    {
        Queue::fake();

        $order = $this->place();

        Queue::assertPushed(ExpireOrderJob::class, fn (ExpireOrderJob $job): bool => $job->orderId === $order->id->value
            && $job->sodaId === $this->soda->id
            && $job->delay == now()->addMinutes(10)->toDateTimeImmutable());
    }

    /** With the real queue, the order is still pending at 12:09 and cancelled for lack of payment at 12:10. */
    public function test_unpaid_order_is_cancelled_by_the_queue_at_its_deadline(): void
    {
        config(['queue.default' => 'database']);
        $order = $this->place();

        $this->travelTo('2026-10-05 12:09:59');
        $this->work();
        $this->assertSame('pending', $this->stored($order)->status);

        $this->travelTo('2026-10-05 12:10:00');
        $this->work();
        $stored = $this->stored($order);
        $this->assertSame('cancelled', $stored->status);
        $this->assertSame('payment_timeout', $stored->cancellation_origin);
        $this->assertSame(5, $this->dish->refresh()->available_portions);
        $this->assertStringContainsString('NotifyCustomerJob', (string) DB::table('jobs')->value('payload'));
    }

    /** An order paid in time is left alone when its job runs. */
    public function test_paid_order_survives_its_deadline(): void
    {
        Queue::fake();
        $order = $this->place();
        $this->travelTo('2026-10-05 12:05:00');
        $this->scopeQueriesTo($this->soda->id);
        OrderModel::query()->whereKey($order->id->value)->update(['payment_status' => 'approved', 'paid_at' => now()]);

        $this->travelTo('2026-10-05 12:10:00');
        app()->call([new ExpireOrderJob($order->id->value, $this->soda->id), 'handle']);

        $this->assertSame('pending', $this->stored($order)->status);
    }

    /** Place an order of one casado, as the soda. */
    private function place(): Order
    {
        $customer = UserModel::factory()->create();

        return app(SodaTransaction::class)->run($this->soda->id, fn (): Order => app(PlaceOrder::class)->execute(
            new PlaceOrderCommand($customer->id, $this->soda->id, [
                ['dish_id' => $this->dish->id, 'dish_name' => $this->dish->name, 'unit_price' => 2_800, 'quantity' => 1],
            ]),
            now()->toDateTimeImmutable(),
        ));
    }

    /** Run the next available job of the database queue, without waiting when there is none. */
    private function work(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->assertSuccessful();
    }

    /** The stored row of the order, read in its soda. */
    private function stored(Order $order): OrderModel
    {
        $this->scopeQueriesTo($this->soda->id);

        return OrderModel::query()->findOrFail($order->id->value);
    }
}
