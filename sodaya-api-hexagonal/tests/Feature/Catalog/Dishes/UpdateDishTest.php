<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Dishes;

use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class UpdateDishTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private DishModel $dish;

    /** Create the dish of the current soda every scenario edits. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();

        $this->dish = DishModel::factory()->create([
            'soda_id' => $this->soda->id,
            'name' => 'Casado de pollo',
            'price' => 2800,
        ]);
    }

    /** Only the fields sent are changed. */
    public function test_owner_updates_only_the_fields_sent(): void
    {
        $this->patchJson($this->endpoint(), ['precio' => 3000])
            ->assertOk()
            ->assertJsonPath('data.precio', 3000)
            ->assertJsonPath('data.nombre', 'Casado de pollo');

        $this->assertDatabaseHas('dishes', ['id' => $this->dish->id, 'price' => 3000, 'name' => 'Casado de pollo']);
    }

    /** The active flag takes the dish off the menu and puts it back. */
    public function test_owner_deactivates_and_reactivates_a_dish(): void
    {
        $this->patchJson($this->endpoint(), ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);
        $this->assertDatabaseHas('dishes', ['id' => $this->dish->id, 'is_active' => false]);

        $this->patchJson($this->endpoint(), ['activo' => true])->assertOk()->assertJsonPath('data.activo', true);
        $this->assertDatabaseCount('dishes', 1);
    }

    /** Keeping the current name does not trip the uniqueness rule. */
    public function test_dish_can_keep_its_own_name(): void
    {
        $this->patchJson($this->endpoint(), ['nombre' => 'Casado de pollo', 'precio' => 2900])->assertOk();
    }

    /** Renaming to the name of another dish of the soda is rejected. */
    public function test_name_of_another_dish_is_rejected(): void
    {
        DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Casado de pescado']);

        $this->patchJson($this->endpoint(), ['nombre' => 'Casado de pescado'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.nombre.0', 'El valor del campo nombre ya está en uso.');
    }

    /** Invalid values are reported per field in Spanish. */
    public function test_invalid_data_is_rejected(): void
    {
        $this->patchJson($this->endpoint(), ['precio' => 50, 'activo' => 'tal vez'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.precio.0', 'El campo precio debe estar entre 100 y 100000.')
            ->assertJsonPath('errors.activo.0', 'El campo activo debe ser verdadero o falso.');
    }

    /** Identity and ownership cannot be changed through the payload. */
    public function test_identity_and_soda_cannot_be_reassigned(): void
    {
        $otherSoda = SodaModel::factory()->create();

        $this->patchJson($this->endpoint(), ['id' => $otherSoda->id, 'soda_id' => $otherSoda->id, 'precio' => 3000])
            ->assertOk()
            ->assertJsonPath('data.id', $this->dish->id);

        $this->assertDatabaseHas('dishes', ['id' => $this->dish->id, 'soda_id' => $this->soda->id]);
    }

    /** Missing dishes and dishes of other sodas answer the same 404. */
    public function test_missing_and_foreign_dishes_are_not_found(): void
    {
        $foreign = DishModel::factory()->create(['price' => 2_500]);
        $payload = ['precio' => 3000];

        $foreignResponse = $this->patchJson($this->endpoint($foreign->id), $payload);
        $missingResponse = $this->patchJson($this->endpoint('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99'), $payload);

        $foreignResponse->assertNotFound()->assertExactJson(['message' => 'El plato no existe.']);
        $this->assertSame($missingResponse->json(), $foreignResponse->json());
        $this->assertDatabaseMissing('dishes', ['id' => $foreign->id, 'price' => 3000]);
    }

    /** Build the endpoint of a dish, by default the scenario one. */
    private function endpoint(?string $dishId = null): string
    {
        return '/api/v1/cocina/platos/'.($dishId ?? $this->dish->id);
    }
}
