<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Categories;

use Illuminate\Support\Arr;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class UpdateCategoryTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private CategoryModel $category;

    /** Create the category every scenario edits. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();

        $this->category = CategoryModel::factory()->create([
            'soda_id' => $this->soda->id,
            'name' => 'Bebidas',
        ]);
    }

    /** The owner can rename a category. */
    public function test_owner_renames_a_category(): void
    {
        $this->patchJson($this->endpoint(), [
            'nombre' => 'Bebidas frías',
        ])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Bebidas frías');

        $this->assertDatabaseHas('categories', [
            'id' => $this->category->id,
            'name' => 'Bebidas frías',
        ]);
    }

    /** Keeping its current name does not violate uniqueness. */
    public function test_category_can_keep_its_own_name(): void
    {
        $this->patchJson($this->endpoint(), [
            'nombre' => 'Bebidas',
        ])->assertOk();
    }

    /** A rename request requires the new name. */
    public function test_name_is_required_to_rename_a_category(): void
    {
        $this->patchJson($this->endpoint(), [])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errores.nombre.0',
                'El campo nombre es obligatorio.',
            );
    }

    /** A name used by another category of the soda is rejected. */
    public function test_name_of_another_category_is_rejected(): void
    {
        CategoryModel::factory()->create([
            'soda_id' => $this->soda->id,
            'name' => 'Postres',
        ]);

        $this->patchJson($this->endpoint(), [
            'nombre' => 'Postres',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errores.nombre.0',
                'El valor del campo nombre ya está en uso.',
            );
    }

    /** Identity and soda cannot be changed through the payload. */
    public function test_identity_and_soda_cannot_be_reassigned(): void
    {
        $otherSoda = SodaModel::factory()->create();

        $this->patchJson($this->endpoint(), [
            'id' => $otherSoda->id,
            'soda_id' => $otherSoda->id,
            'nombre' => 'Comidas',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $this->category->id);

        $this->assertDatabaseHas('categories', [
            'id' => $this->category->id,
            'soda_id' => $this->soda->id,
            'name' => 'Comidas',
        ]);
    }

    /** Missing and foreign categories answer the same 404. */
    public function test_missing_and_foreign_categories_are_not_found(): void
    {
        $foreign = CategoryModel::factory()->create([
            'name' => 'Extranjera',
        ]);

        $payload = ['nombre' => 'Nuevo nombre'];

        $foreignResponse = $this->patchJson(
            $this->endpoint($foreign->id),
            $payload,
        );

        $missingResponse = $this->patchJson(
            $this->endpoint('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99'),
            $payload,
        );

        $foreignResponse->assertNotFound();

        $this->assertSame(
            Arr::except($missingResponse->json(), 'instance'),
            Arr::except($foreignResponse->json(), 'instance'),
        );

        $this->assertDatabaseHas('categories', [
            'id' => $foreign->id,
            'name' => 'Extranjera',
        ]);
    }

    /** Build the endpoint of a category. */
    private function endpoint(?string $categoryId = null): string
    {
        return '/api/v1/cocina/categorias/'.($categoryId ?? $this->category->id);
    }
}
