<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Facades\DB;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Application\DTOs\AdvanceOrderCommand;
use Src\Ordering\Orders\Application\DTOs\ApproveOrderPaymentCommand;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Application\UseCases\AdvanceOrder;
use Src\Ordering\Orders\Application\UseCases\ApproveOrderPayment;
use Src\Ordering\Orders\Application\UseCases\PlaceOrder;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class UnconfirmedOrderExpiryTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private SodaModel $soda;

    private DishModel $dish;

    private Order $order;

    /** An order placed at 12:00 with the real queue, paid at 12:04. */
    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
        $this->travelTo('2026-10-05 12:00:00');
        $this->soda = SodaModel::factory()->create();
        TimeSlotModel::factory()->create(['soda_id' => $this->soda->id, 'day_of_week' => 1, 'opens_at' => '06:00', 'closes_at' => '14:00']);
        $this->dish = DishModel::factory()->create(['soda_id' => $this->soda->id, 'available_portions' => 5, 'price' => 2_800]);
        $customer = UserModel::factory()->create();

        $this->order = $this->asSoda(fn (): Order => app(PlaceOrder::class)->execute(
            new PlaceOrderCommand($customer->id, $this->soda->id, [['dish_id' => $this->dish->id, 'quantity' => 2]], 'toque-1'),
            now()->toDateTimeImmutable(),
        )->order);

        $this->travelTo('2026-10-05 12:04:00');
        $this->asSoda(fn (): Order => app(ApproveOrderPayment::class)->execute(
            new ApproveOrderPaymentCommand($this->order->id->value, $this->soda->id),
            now()->toDateTimeImmutable(),
        ));
    }

    /** At 12:10 the order is paid and stays; at 12:14 nobody confirmed it, so it is cancelled and refunded. */
    public function test_paid_order_the_kitchen_does_not_confirm_is_cancelled_and_refunded(): void
    {
        $this->travelTo('2026-10-05 12:10:00');
        $this->work();
        $this->assertSame('pending', $this->stored()->status);

        $this->travelTo('2026-10-05 12:14:00');
        $this->work();

        $stored = $this->stored();
        $this->assertSame('cancelled', $stored->status);
        $this->assertSame('confirmation_timeout', $stored->cancellation_origin);
        $this->assertSame(5, $this->dish->refresh()->available_portions);
        $this->assertStringContainsString('RequestRefundJob', (string) DB::table('jobs')->orderBy('id')->value('payload'));
    }

    /** An order the kitchen confirms in time is left alone when its deadline arrives. */
    public function test_order_confirmed_in_time_is_kept(): void
    {
        $this->travelTo('2026-10-05 12:06:00');
        $this->asSoda(fn (): Order => app(AdvanceOrder::class)->execute(
            new AdvanceOrderCommand($this->order->id->value, $this->soda->id, 'confirmed'),
            now()->toDateTimeImmutable(),
        ));

        $this->travelTo('2026-10-05 12:14:00');
        $this->work();
        $this->work();

        $this->assertSame('confirmed', $this->stored()->status);
        $this->assertSame(0, DB::table('jobs')->count());
    }

    /**
     * Run an operation in a transaction of the soda, as a request of its staff would.
     *
     * @template TResult
     *
     * @param  \Closure(): TResult  $operation
     * @return TResult
     */
    private function asSoda(\Closure $operation): mixed
    {
        return app(SodaTransaction::class)->run($this->soda->id, $operation);
    }

    /** Run the next available job of the database queue, without waiting when there is none. */
    private function work(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0])->assertSuccessful();
    }

    /** The stored row of the order, read in its soda. */
    private function stored(): OrderModel
    {
        $this->scopeQueriesTo($this->soda->id);

        return OrderModel::query()->findOrFail($this->order->id->value);
    }
}
