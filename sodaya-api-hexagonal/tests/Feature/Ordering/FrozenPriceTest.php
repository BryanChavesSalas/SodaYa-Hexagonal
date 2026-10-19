<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class FrozenPriceTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private SodaModel $soda;

    private UserModel $customer;

    private DishModel $casado;

    /** A soda open on Monday with a casado at ₡2800, and a customer ordering at noon. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->soda = SodaModel::factory()->create();
        TimeSlotModel::factory()->create(['soda_id' => $this->soda->id, 'day_of_week' => 1, 'opens_at' => '06:00', 'closes_at' => '14:00']);
        $this->casado = DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Casado de pollo', 'price' => 2_800, 'available_portions' => 10]);
        $this->customer = UserModel::factory()->create();

        Sanctum::actingAs($this->customer, Role::Customer->abilities());
    }

    /** Whatever price, subtotal or total the client sends is ignored: the line takes the price of the catalog. */
    public function test_amounts_sent_by_the_client_are_ignored(): void
    {
        $response = $this->postJson($this->endpoint(), [
            'lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 2, 'precio_unitario' => 1, 'subtotal' => 1, 'nombre' => 'Langosta']],
            'total' => 1,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.lineas.0.nombre', 'Casado de pollo')
            ->assertJsonPath('data.lineas.0.precio_unitario', 2_800)
            ->assertJsonPath('data.lineas.0.subtotal', 5_600)
            ->assertJsonPath('data.total', 5_600);

        $this->assertDatabaseHas('order_lines', ['order_id' => $response->json('data.id'), 'dish_name' => 'Casado de pollo', 'unit_price' => 2_800]);
    }

    /** A later change of the price in the catalog leaves the orders already placed as they were. */
    public function test_price_change_after_ordering_keeps_the_order_as_it_was(): void
    {
        $before = $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 2]]])->assertCreated();

        Sanctum::actingAs(UserModel::factory()->create(['role' => Role::Owner->value, 'soda_id' => $this->soda->id]), Role::Owner->abilities());
        $this->patchJson("/api/v1/cocina/platos/{$this->casado->id}", ['precio' => 3_500])->assertOk();

        Sanctum::actingAs($this->customer, Role::Customer->abilities());
        $after = $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 2]]])->assertCreated();

        $this->assertDatabaseHas('order_lines', ['order_id' => $before->json('data.id'), 'unit_price' => 2_800]);
        $this->assertDatabaseHas('order_lines', ['order_id' => $after->json('data.id'), 'unit_price' => 3_500]);
        $this->assertSame(7_000, $after->json('data.total'));
    }

    /** The total is derived from the lines on every read: no column stores it. */
    public function test_total_is_not_stored(): void
    {
        $this->assertFalse(Schema::hasColumn('orders', 'total'));
        $this->assertFalse(Schema::hasColumn('order_lines', 'subtotal'));
    }

    /** Address of the orders of the soda. */
    private function endpoint(): string
    {
        return "/api/v1/sodas/{$this->soda->id}/pedidos";
    }
}
