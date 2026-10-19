<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Database\Factories\OrderFactory;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class KitchenListsOrdersTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/pedidos';

    /** @var array<string, OrderModel> one order of the soda in each status, by the status of the API, placed one every ten minutes from 11:00 */
    private array $orders;

    /** Authenticate the kitchen of a soda on a Monday at noon, with one order in each status and an order of another soda. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->actOnNewSoda(Role::Kitchen);

        $states = ['entregado' => 'delivered', 'listo' => 'ready', 'confirmado' => 'confirmed', 'pendiente' => 'new', 'cancelado' => 'cancelled'];
        $minute = 0;

        foreach ($states as $status => $state) {
            $this->orders[$status] = $this->orderOf(OrderModel::factory()->{$state}(), sprintf('2026-10-05 11:%02d:00', $minute));
            $minute += 10;
        }

        OrderLineModel::factory()->create();
    }

    /** Without a filter the kitchen sees every order of its soda, oldest first, each with its number, its estimated time and its lines. */
    public function test_kitchen_lists_every_order_of_the_soda_oldest_first(): void
    {
        $first = $this->orders['entregado'];

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.*.id', array_values(array_map(fn (OrderModel $order): string => $order->id, $this->orders)))
            ->assertJsonPath('data.0.numero_retiro', $first->pickup_number)
            ->assertJsonPath('data.0.creado_en', '2026-10-05T11:00:00-06:00')
            ->assertJsonPath('data.0.retiro_estimado', '2026-10-05T11:20:00-06:00')
            ->assertJsonPath('data.0.lineas.0.cantidad', 2)
            ->assertJsonPath('meta', ['pagina' => 1, 'por_pagina' => 15, 'total' => 5, 'ultima_pagina' => 1]);
    }

    /** With a status the kitchen sees only the orders in that status. */
    #[DataProvider('statuses')]
    public function test_kitchen_filters_by_status(string $status): void
    {
        $this->getJson(self::ENDPOINT.'?estado='.$status)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->orders[$status]->id])
            ->assertJsonPath('data.0.estado', $status)
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * Statuses of the API.
     *
     * @return array<string, array{string}>
     */
    public static function statuses(): array
    {
        return array_map(fn (string $status): array => [$status], array_combine(
            ['pendiente', 'confirmado', 'listo', 'entregado', 'cancelado'],
            ['pendiente', 'confirmado', 'listo', 'entregado', 'cancelado'],
        ));
    }

    /** A status outside the five of the domain is invalid data. */
    public function test_unknown_status_is_invalid_data(): void
    {
        $this->getJson(self::ENDPOINT.'?estado=pagado')
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonStructure(['errores' => ['estado']]);
    }

    /** The owner lists the orders too; a customer is forbidden. */
    public function test_owner_lists_and_customer_is_forbidden(): void
    {
        $this->actAsStaffOf($this->soda, Role::Owner);
        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('meta.total', 5);

        Sanctum::actingAs(UserModel::factory()->create(), Role::Customer->abilities());
        $this->getJson(self::ENDPOINT)->assertForbidden()->assertJsonPath('type', 'http://localhost/problemas/prohibido');
    }

    /** Create an order of the current soda placed at the given moment, with one line of two portions of a dish of 20 minutes. */
    private function orderOf(OrderFactory $factory, string $createdAt): OrderModel
    {
        $order = $factory->create(['soda_id' => $this->soda->id, 'created_at' => $createdAt]);
        OrderLineModel::factory()->for($order, 'order')->create(['quantity' => 2, 'preparation_minutes' => 20]);

        return $order;
    }
}
