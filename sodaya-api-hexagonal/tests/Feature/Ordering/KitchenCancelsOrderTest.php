<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Database\Factories\OrderFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Ordering\Orders\Application\Contracts\RefundRequests;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\Ordering\FakeRefundRequests;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class KitchenCancelsOrderTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string MISSING_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    private FakeRefundRequests $refunds;

    private DishModel $dish;

    /** Authenticate the kitchen of a soda on a Monday at noon; its dish has 5 portions left. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->actOnNewSoda(Role::Kitchen);
        $this->dish = DishModel::factory()->create(['soda_id' => $this->soda->id, 'available_portions' => 5]);
        $this->refunds = new FakeRefundRequests;
        $this->app->instance(RefundRequests::class, $this->refunds);
    }

    /** The kitchen cancels a pending or confirmed order, the origin is the kitchen and the portions go back to the menu. */
    #[DataProvider('cancellableStates')]
    public function test_kitchen_cancels_a_cancellable_order(string $state): void
    {
        $order = $this->orderOfTwoPortions(OrderModel::factory()->{$state}());

        $this->postJson($this->endpoint($order->id))
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado')
            ->assertJsonPath('data.cancelado_por', 'cocina')
            ->assertJsonPath('data.cancelado_en', now()->format(DATE_ATOM));

        $this->assertSame('kitchen', $order->refresh()->cancellation_origin);
        $this->assertSame(7, $this->dish->refresh()->available_portions);
    }

    /**
     * Factory states the kitchen can cancel.
     *
     * @return array<string, array{string}>
     */
    public static function cancellableStates(): array
    {
        return [
            'pending' => ['paid'],
            'confirmed' => ['confirmed'],
        ];
    }

    /** A paid order asks for its refund; an unpaid one does not. */
    public function test_only_a_paid_order_asks_for_its_refund(): void
    {
        $paid = $this->orderOfTwoPortions(OrderModel::factory()->confirmed());
        $unpaid = $this->orderOfTwoPortions(OrderModel::factory());

        $this->postJson($this->endpoint($paid->id))->assertOk();
        $this->postJson($this->endpoint($unpaid->id))->assertOk();

        $this->assertSame([$paid->id], $this->refunds->requested);
    }

    /** A ready or delivered order answers 409 and keeps its status and its portions sold. */
    #[DataProvider('finishedStates')]
    public function test_ready_or_delivered_order_is_not_cancelled(string $state, string $status): void
    {
        $order = $this->orderOfTwoPortions(OrderModel::factory()->{$state}());

        $this->postJson($this->endpoint($order->id))
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/transicion-no-permitida');

        $this->assertSame($status, $order->refresh()->status);
        $this->assertSame(5, $this->dish->refresh()->available_portions);
        $this->assertSame([], $this->refunds->requested);
    }

    /**
     * Factory states past the point of cancelling and the status they keep.
     *
     * @return array<string, array{string, string}>
     */
    public static function finishedStates(): array
    {
        return [
            'ready' => ['ready', 'ready'],
            'delivered' => ['delivered', 'delivered'],
        ];
    }

    /** Cancelling a cancelled order keeps its first origin and gives nothing back twice. */
    public function test_cancelled_order_stays_as_it_was(): void
    {
        $order = $this->orderOfTwoPortions(OrderModel::factory()->cancelled('customer'));

        $this->postJson($this->endpoint($order->id))
            ->assertOk()
            ->assertJsonPath('data.cancelado_por', 'cliente');

        $this->assertSame(5, $this->dish->refresh()->available_portions);
    }

    /** The status history keeps the cancellation by the kitchen. */
    public function test_cancellation_is_recorded_in_the_history(): void
    {
        $order = $this->orderOfTwoPortions(OrderModel::factory()->confirmed());

        $this->postJson($this->endpoint($order->id))->assertOk();

        $this->assertSame(
            [['from_status' => 'confirmed', 'to_status' => 'cancelled']],
            DB::table('order_status_history')->where('order_id', $order->id)->get(['from_status', 'to_status'])->map(fn (object $row): array => (array) $row)->all(),
        );
    }

    /** An order of another soda answers the same 404 as one that does not exist, and stays pending. */
    public function test_order_of_another_soda_answers_like_a_missing_one(): void
    {
        $foreign = OrderModel::factory()->create();

        $foreignResponse = $this->postJson($this->endpoint($foreign->id))->assertNotFound()->assertJsonPath('type', 'http://localhost/problemas/pedido-no-encontrado');
        $missingResponse = $this->postJson($this->endpoint(self::MISSING_ID))->assertNotFound();

        $this->assertSame(Arr::except($missingResponse->json(), 'instance'), Arr::except($foreignResponse->json(), 'instance'));
        $this->scopeQueriesTo($foreign->soda_id);
        $this->assertSame('pending', $foreign->refresh()->status);
    }

    /** Create an order of the current soda with one line of two portions of the dish. */
    private function orderOfTwoPortions(OrderFactory $factory): OrderModel
    {
        $order = $factory->create(['soda_id' => $this->soda->id]);
        OrderLineModel::factory()->for($order, 'order')->create(['dish_id' => $this->dish->id, 'quantity' => 2]);

        return $order;
    }

    /** Address of the cancellation of an order. */
    private function endpoint(string $orderId): string
    {
        return "/api/v1/cocina/pedidos/{$orderId}/cancelacion";
    }
}
