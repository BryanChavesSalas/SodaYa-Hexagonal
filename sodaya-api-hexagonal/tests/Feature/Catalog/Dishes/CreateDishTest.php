<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Dishes;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CreateDishTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/platos';

    /** Make a fresh soda the current tenant. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();
    }

    /** A valid dish is stored and returned with its location. */
    public function test_owner_creates_a_dish(): void
    {
        $category = CategoryModel::factory()->create(['soda_id' => $this->soda->id]);

        $response = $this->postJson(self::ENDPOINT, $this->payload(['categoria_id' => $category->id]));

        $dishId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertHeader('Location', url(self::ENDPOINT.'/'.$dishId))
            ->assertExactJson(['data' => [
                'id' => $dishId,
                'nombre' => 'Casado de pollo',
                'descripcion' => 'Con arroz, frijoles y ensalada',
                'precio' => 2800,
                'minutos_preparacion' => 15,
                'porciones_disponibles' => 10,
                'categoria_id' => $category->id,
                'activo' => true,
                'agotado' => false,
            ]]);

        $this->assertDatabaseHas('dishes', ['id' => $dishId, 'soda_id' => $this->soda->id]);
    }

    /** The dish belongs to the current soda even if the payload names another. */
    public function test_soda_sent_in_the_payload_is_ignored(): void
    {
        $otherSoda = SodaModel::factory()->create();

        $this->postJson(self::ENDPOINT, $this->payload(['soda_id' => $otherSoda->id]))->assertCreated();

        $this->assertDatabaseHas('dishes', ['soda_id' => $this->soda->id]);
        $this->assertDatabaseMissing('dishes', ['soda_id' => $otherSoda->id]);
    }

    /** Invalid fields are reported one by one in Spanish. */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_data_is_rejected(string $field, mixed $value, string $message): void
    {
        $this->postJson(self::ENDPOINT, $this->payload([$field => $value]))
            ->assertUnprocessable()
            ->assertJsonPath("errores.{$field}.0", $message);

        $this->assertDatabaseCount('dishes', 0);
    }

    /**
     * Invalid values for a field and the message each one produces.
     *
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'missing name' => ['nombre', null, 'El campo nombre es obligatorio.'],
            'name too long' => ['nombre', str_repeat('a', 121), 'El campo nombre no debe tener más de 120 caracteres.'],
            'price below minimum' => ['precio', 99, 'El campo precio debe estar entre 100 y 100000.'],
            'price with decimals' => ['precio', 2800.5, 'El campo precio debe ser un número entero.'],
            'preparation time above maximum' => [
                'minutos_preparacion',
                121,
                'El campo minutos de preparación debe estar entre 1 y 120.',
            ],
            'negative portions' => [
                'porciones_disponibles',
                -1,
                'El campo porciones disponibles debe estar entre 0 y 500.',
            ],
            'malformed category' => ['categoria_id', 'abc', 'El campo categoría debe ser un UUID válido.'],
        ];
    }

    /** A name already used in the soda is rejected. */
    public function test_repeated_name_in_the_soda_is_rejected(): void
    {
        DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Casado de pollo']);

        $this->postJson(self::ENDPOINT, $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('errores.nombre.0', 'El valor del campo nombre ya está en uso.');
    }

    /** The same name is accepted when another soda uses it. */
    public function test_name_used_by_another_soda_is_accepted(): void
    {
        DishModel::factory()->create(['name' => 'Casado de pollo']);

        $this->postJson(self::ENDPOINT, $this->payload())->assertCreated();
    }

    /** Unknown and foreign categories produce the same response. */
    public function test_foreign_category_is_indistinguishable_from_a_missing_one(): void
    {
        $foreign = CategoryModel::factory()->create();
        $missing = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

        $foreignResponse = $this->postJson(self::ENDPOINT, $this->payload(['categoria_id' => $foreign->id]));
        $missingResponse = $this->postJson(self::ENDPOINT, $this->payload(['categoria_id' => $missing]));

        $foreignResponse->assertUnprocessable();
        $this->assertSame(
            Arr::except($missingResponse->json(), 'instance'),
            Arr::except($foreignResponse->json(), 'instance'),
        );
    }

    /**
     * Build a valid payload with optional overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'nombre' => 'Casado de pollo',
            'descripcion' => 'Con arroz, frijoles y ensalada',
            'precio' => 2800,
            'minutos_preparacion' => 15,
            'porciones_disponibles' => 10,
            ...$overrides,
        ];
    }
}
