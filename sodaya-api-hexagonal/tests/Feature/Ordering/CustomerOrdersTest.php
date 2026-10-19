<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Database\Factories\OrderFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CustomerOrdersTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/pedidos';

    private const string MISSING_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    private SodaModel $soda;

    private UserModel $customer;

    /** A customer authenticated on a Monday at noon, with a soda to order from. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->soda = SodaModel::factory()->create();
        $this->customer = UserModel::factory()->create();

        Sanctum::actingAs($this->customer, Role::Customer->abilities());
    }

    /** The listing holds the orders of the customer at every soda, newest first, and none of anyone else. */
    public function test_customer_lists_only_their_orders_newest_first(): void
    {
        $oldest = $this->orderOf(OrderModel::factory(), '2026-10-05 11:00:00');
        $elsewhere = $this->orderOf(OrderModel::factory(), '2026-10-05 11:30:00', soda: SodaModel::factory()->create());
        $newest = $this->orderOf(OrderModel::factory()->confirmed(), '2026-10-05 11:45:00');
        $this->orderOf(OrderModel::factory(), '2026-10-05 11:50:00', customer: UserModel::factory()->create());

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$newest->id, $elsewhere->id, $oldest->id])
            ->assertJsonPath('data.0.estado', 'confirmado')
            ->assertJsonPath('data.0.lineas.0.cantidad', 2)
            ->assertJsonPath('meta', ['pagina' => 1, 'por_pagina' => 15, 'total' => 3, 'ultima_pagina' => 1]);
    }

    /** The listing is paginated: 15 by default, 50 at most, and the pages do not repeat orders. */
    public function test_listing_is_paginated(): void
    {
        for ($i = 0; $i < 16; $i++) {
            $this->orderOf(OrderModel::factory(), sprintf('2026-10-05 %02d:%02d:00', 7 + intdiv($i, 4), ($i % 4) * 15));
        }

        $first = $this->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.ultima_pagina', 2);
        $second = $this->getJson(self::ENDPOINT.'?pagina=2')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::ENDPOINT.'?por_pagina=50')->assertOk()->assertJsonCount(16, 'data');
        $this->getJson(self::ENDPOINT.'?por_pagina=51')->assertUnprocessable()->assertJsonStructure(['errores' => ['por_pagina']]);

        $this->assertSame([], array_intersect($first->json('data.*.id'), $second->json('data.*.id')));
    }

    /** The listing runs the same queries for one order as for twenty: the lines come in one query, not one per order. */
    public function test_listing_runs_the_same_queries_for_one_order_or_twenty(): void
    {
        $this->orderOf(OrderModel::factory(), '2026-10-05 11:00:00');
        $withOne = $this->queriesOf(self::ENDPOINT.'?por_pagina=50');

        for ($i = 1; $i < 20; $i++) {
            $this->orderOf(OrderModel::factory(), sprintf('2026-10-05 11:%02d:00', $i));
        }

        $this->assertSame($withOne, $this->queriesOf(self::ENDPOINT.'?por_pagina=50'));
    }

    /** The detail describes the order with its lines, its pickup number, its estimated pickup time and its actions. */
    public function test_customer_reads_the_detail_of_their_order(): void
    {
        $order = $this->orderOf(OrderModel::factory()->state(['pickup_number' => 7]), '2026-10-05 11:00:00');
        $line = $order->lines()->sole();

        $this->getJson(self::ENDPOINT.'/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.numero_retiro', 7)
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.creado_en', '2026-10-05T11:00:00-06:00')
            ->assertJsonPath('data.retiro_estimado', '2026-10-05T11:20:00-06:00')
            ->assertJsonPath('data.lineas.0.plato_id', $line->dish_id)
            ->assertJsonPath('data.total', $line->unit_price * 2)
            ->assertJsonPath('data.acciones', [
                'pagar' => "http://localhost/api/v1/pedidos/{$order->id}/pago",
                'cancelar' => "http://localhost/api/v1/pedidos/{$order->id}/cancelacion",
            ]);
    }

    /** The actions of an order depend on its status: pay while pending and unpaid, cancel while pending or confirmed, nothing at the end. */
    #[DataProvider('actionsByStatus')]
    public function test_actions_depend_on_the_status(string $state, array $actions): void
    {
        $order = $this->orderOf(OrderModel::factory()->{$state}(), '2026-10-05 11:00:00');
        $expected = array_map(fn (string $segment): string => "http://localhost/api/v1/pedidos/{$order->id}/{$segment}", $actions);

        $detail = $this->getJson(self::ENDPOINT.'/'.$order->id)->assertOk();
        $listing = $this->getJson(self::ENDPOINT)->assertOk();

        $this->assertSame($expected, $detail->json('data.acciones'));
        $this->assertSame($expected, $listing->json('data.0.acciones'));
        $this->assertStringContainsString('"acciones":{', (string) $detail->getContent());
    }

    /**
     * Factory states paired with the actions available in them: the name of each action and the segment of its link.
     *
     * @return array<string, array{string, array<string, string>}>
     */
    public static function actionsByStatus(): array
    {
        return [
            'pending and unpaid' => ['new', ['pagar' => 'pago', 'cancelar' => 'cancelacion']],
            'pending and paid' => ['paid', ['cancelar' => 'cancelacion']],
            'confirmed' => ['confirmed', ['cancelar' => 'cancelacion']],
            'ready' => ['ready', []],
            'delivered' => ['delivered', []],
            'cancelled' => ['cancelled', []],
        ];
    }

    /** An order of another customer answers the same 404 pedido-no-encontrado as an order that does not exist. */
    public function test_order_of_another_customer_answers_like_a_missing_one(): void
    {
        $foreign = $this->orderOf(OrderModel::factory(), '2026-10-05 11:00:00', customer: UserModel::factory()->create());

        $foreignResponse = $this->getJson(self::ENDPOINT.'/'.$foreign->id)->assertNotFound();
        $missingResponse = $this->getJson(self::ENDPOINT.'/'.self::MISSING_ID)->assertNotFound();

        $foreignResponse
            ->assertJsonPath('type', 'http://localhost/problemas/pedido-no-encontrado')
            ->assertJsonPath('detail', 'El pedido no existe.');
        $this->assertSame(Arr::except($missingResponse->json(), 'instance'), Arr::except($foreignResponse->json(), 'instance'));
    }

    /** Only a customer reads their orders: the staff is forbidden and a request without token is not authenticated. */
    public function test_only_customers_read_their_orders(): void
    {
        Sanctum::actingAs(UserModel::factory()->kitchen()->create(), Role::Kitchen->abilities());
        $this->getJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('type', 'http://localhost/problemas/prohibido');

        $this->app['auth']->forgetGuards();
        $this->getJson(self::ENDPOINT, ['Authorization' => ''])->assertUnauthorized();
    }

    /** Number of queries a request runs. */
    private function queriesOf(string $endpoint): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($endpoint)->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    /** Create an order placed at the given moment, of the authenticated customer at the soda unless told otherwise, with one line of two portions of a dish of 20 minutes. */
    private function orderOf(OrderFactory $factory, string $createdAt, ?UserModel $customer = null, ?SodaModel $soda = null): OrderModel
    {
        $order = $factory->create([
            'soda_id' => ($soda ?? $this->soda)->id,
            'customer_id' => ($customer ?? $this->customer)->id,
            'created_at' => $createdAt,
        ]);
        OrderLineModel::factory()->for($order, 'order')->create(['quantity' => 2, 'preparation_minutes' => 20]);

        return $order;
    }
}
