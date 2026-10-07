<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Categories;

use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CreateCategoryTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/categorias';

    /** Make a fresh soda the current tenant. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();
    }

    /** A valid category is stored and returned with its location. */
    public function test_owner_creates_a_category(): void
    {
        $response = $this->postJson(self::ENDPOINT, [
            'nombre' => 'Bebidas',
        ]);

        $categoryId = $response->json('data.id');

        $response
            ->assertCreated()
            ->assertHeader('Location', url(self::ENDPOINT.'/'.$categoryId))
            ->assertExactJson([
                'data' => [
                    'id' => $categoryId,
                    'nombre' => 'Bebidas',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'soda_id' => $this->soda->id,
            'name' => 'Bebidas',
        ]);
    }

    /** The current soda is used even if another soda is sent in the payload. */
    public function test_soda_sent_in_the_payload_is_ignored(): void
    {
        $otherSoda = SodaModel::factory()->create();

        $this->postJson(self::ENDPOINT, [
            'nombre' => 'Postres',
            'soda_id' => $otherSoda->id,
        ])->assertCreated();

        $this->assertDatabaseHas('categories', [
            'soda_id' => $this->soda->id,
            'name' => 'Postres',
        ]);

        $this->assertDatabaseMissing('categories', [
            'soda_id' => $otherSoda->id,
            'name' => 'Postres',
        ]);
    }

    /** A repeated name inside the current soda is rejected. */
    public function test_repeated_name_in_the_soda_is_rejected(): void
    {
        CategoryModel::factory()->create([
            'soda_id' => $this->soda->id,
            'name' => 'Bebidas',
        ]);

        $this->postJson(self::ENDPOINT, ['nombre' => 'Bebidas'])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errores.nombre.0',
                'El valor del campo nombre ya está en uso.',
            );
    }

    /** The same category name may be used by another soda. */
    public function test_name_used_by_another_soda_is_accepted(): void
    {
        CategoryModel::factory()->create([
            'name' => 'Bebidas',
        ]);

        $this->postJson(self::ENDPOINT, [
            'nombre' => 'Bebidas',
        ])->assertCreated();
    }

    /** Invalid names are rejected. */
    public function test_invalid_name_is_rejected(): void
    {
        $this->postJson(self::ENDPOINT, [
            'nombre' => str_repeat('a', 61),
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errores.nombre.0',
                'El campo nombre no debe tener más de 60 caracteres.',
            );
    }
}
