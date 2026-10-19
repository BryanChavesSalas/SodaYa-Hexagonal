<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Database\Factories\OrderFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Application\Contracts\CustomerNotices;
use Src\Ordering\Orders\Application\Contracts\RefundRequests;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Ordering\FakeCustomerNotices;
use Tests\Support\Ordering\FakeRefundRequests;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class CustomerCancelsOrderTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private const string MISSING_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    private SodaModel $soda;

    private UserModel $customer;

    private DishModel $dish;

    private FakeRefundRequests $refunds;

    private FakeCustomerNotices $notices;

    /** A customer authenticated on a Monday at noon; the soda has a dish with 5 portions left. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->soda = SodaModel::factory()->create();
        $this->dish = DishModel::factory()->create(['soda_id' => $this->soda->id, 'available_portions' => 5]);
        $this->customer = UserModel::factory()->create();
        $this->refunds = new FakeRefundRequests;
        $this->notices = new FakeCustomerNotices;
        $this->app->instance(RefundRequests::class, $this->refunds);
        $this->app->instance(CustomerNotices::class, $this->notices);

        Sanctum::actingAs($this->customer, Role::Customer->abilities());
    }

    /** The customer cancels a pending or confirmed order: the origin is the customer, the portions go back and no action is left. */
    #[DataProvider('cancellableStates')]
    public function test_customer_cancels_a_cancellable_order(string $state): void
    {
        $order = $this->orderOfTwoPortions(OrderModel::factory()->{$state}());

        $this->postJson($this->endpoint($order->id))
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado')
            ->assertJsonPath('data.cancelado_por', 'cliente')
            ->assertJsonPath('data.cancelado_en', now()->format(DATE_ATOM))
            ->assertJsonPath('data.acciones', []);

        $this->scopeQueriesTo($this->soda->id);
        $this->assertSame('customer', $order->refresh()->cancellation_origin);
        $this->assertSame(7, $this->dish->refresh()->available_portions);
    }

    /**
     * Factory states the customer can cancel.
     *
     * @return array<string, array{string}>
     */
    public static function cancellableStates(): array
    {
        return [
            'pending and unpaid' => ['new'],
            'pending and paid' => ['paid'],
            'confirmed' => ['confirmed'],
        ];
    }

    /** A paid order asks for its refund; an unpaid one does not; and the customer is never told what they just did. */
    public function test_paid_order_asks_for_its_refund_and_nobody_notifies_the_customer(): void
    {
        $paid = $this->orderOfTwoPortions(OrderModel::factory()->confirmed());
        $unpaid = $this->orderOfTwoPortions(OrderModel::factory());

        $this->postJson($this->endpoint($paid->id))->assertOk();
        $this->postJson($this->endpoint($unpaid->id))->assertOk();

        $this->assertSame([$paid->id], $this->refunds->requested);
        $this->assertSame([], $this->notices->cancelled);
    }

    /** A ready or delivered order answers 409 transicion-no-permitida and keeps its status and its portions sold. */
    #[DataProvider('finishedStates')]
    public function test_ready_or_delivered_order_is_not_cancelled(string $state): void
    {
        $order = $this->orderOfTwoPortions(OrderModel::factory()->{$state}());

        $this->postJson($this->endpoint($order->id))
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/transicion-no-permitida')
            ->assertJsonPath('detail', 'El pedido no puede pasar a ese estado.');

        $this->scopeQueriesTo($this->soda->id);
        $this->assertSame($state, $order->refresh()->status);
        $this->assertSame(5, $this->dish->refresh()->available_portions);
        $this->assertSame([], $this->refunds->requested);
    }

    /**
     * Factory states past the point of cancelling.
     *
     * @return array<string, array{string}>
     */
    public static function finishedStates(): array
    {
        return [
            'ready' => ['ready'],
            'delivered' => ['delivered'],
        ];
    }

    /** Cancelling a cancelled order keeps its first origin and gives nothing back twice. */
    public function test_cancelled_order_stays_as_it_was(): void
    {
        $order = $this->orderOfTwoPortions(OrderModel::factory()->cancelled('kitchen'));

        $this->postJson($this->endpoint($order->id))
            ->assertOk()
            ->assertJsonPath('data.cancelado_por', 'cocina');

        $this->scopeQueriesTo($this->soda->id);
        $this->assertSame(5, $this->dish->refresh()->available_portions);
    }

    /** The status history keeps the cancellation, written by the trigger with the soda the middleware fixed. */
    public function test_cancellation_is_recorded_in_the_history(): void
    {
        $order = $this->orderOfTwoPortions(OrderModel::factory()->confirmed());

        $this->postJson($this->endpoint($order->id))->assertOk();

        $this->scopeQueriesTo($this->soda->id);
        $this->assertSame(
            [['from_status' => 'confirmed', 'to_status' => 'cancelled']],
            DB::table('order_status_history')->where('order_id', $order->id)->get(['from_status', 'to_status'])->map(fn (object $row): array => (array) $row)->all(),
        );
    }

    /** An order of another customer answers the same 404 pedido-no-encontrado as one that does not exist, and stays as it was. */
    public function test_order_of_another_customer_answers_like_a_missing_one(): void
    {
        $foreign = $this->orderOfTwoPortions(OrderModel::factory(), UserModel::factory()->create());

        $foreignResponse = $this->postJson($this->endpoint($foreign->id))->assertNotFound();
        $missingResponse = $this->postJson($this->endpoint(self::MISSING_ID))->assertNotFound();

        $foreignResponse->assertJsonPath('type', 'http://localhost/problemas/pedido-no-encontrado');
        $this->assertSame(Arr::except($missingResponse->json(), 'instance'), Arr::except($foreignResponse->json(), 'instance'));
        $this->scopeQueriesTo($this->soda->id);
        $this->assertSame('pending', $foreign->refresh()->status);
        $this->assertSame(5, $this->dish->refresh()->available_portions);
    }

    /** Only a customer cancels through this route: the staff is forbidden. */
    public function test_staff_cannot_cancel_through_the_route_of_the_customer(): void
    {
        $order = $this->orderOfTwoPortions(OrderModel::factory());
        Sanctum::actingAs(UserModel::factory()->kitchen()->create(), Role::Kitchen->abilities());

        $this->postJson($this->endpoint($order->id))
            ->assertForbidden()
            ->assertJsonPath('type', 'http://localhost/problemas/prohibido');
    }

    /** Create an order at the soda, of the authenticated customer unless told otherwise, with one line of two portions of the dish. */
    private function orderOfTwoPortions(OrderFactory $factory, ?UserModel $customer = null): OrderModel
    {
        $order = $factory->create(['soda_id' => $this->soda->id, 'customer_id' => ($customer ?? $this->customer)->id]);
        OrderLineModel::factory()->for($order, 'order')->create(['dish_id' => $this->dish->id, 'quantity' => 2]);

        return $order;
    }

    /** Address of the cancellation of an order by its customer. */
    private function endpoint(string $orderId): string
    {
        return "/api/v1/pedidos/{$orderId}/cancelacion";
    }
}
