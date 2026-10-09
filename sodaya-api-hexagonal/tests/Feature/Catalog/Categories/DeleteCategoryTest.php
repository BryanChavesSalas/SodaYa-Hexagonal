<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Categories;

use Illuminate\Support\Arr;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class DeleteCategoryTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private CategoryModel $category;

    /** Create the category every scenario deletes. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();

        $this->category = CategoryModel::factory()->create([
            'soda_id' => $this->soda->id,
            'name' => 'Bebidas',
        ]);
    }

    /** The owner can delete a category. */
    public function test_owner_deletes_a_category(): void
    {
        $this->deleteJson($this->endpoint())
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', [
            'id' => $this->category->id,
        ]);
    }

    /** Deleting a category keeps its dishes without a category. */
    public function test_deleting_category_keeps_its_dishes_uncategorized(): void
    {
        $dish = DishModel::factory()->create([
            'soda_id' => $this->soda->id,
            'category_id' => $this->category->id,
        ]);

        $this->deleteJson($this->endpoint())
            ->assertNoContent();

        $this->assertDatabaseMissing('categories', [
            'id' => $this->category->id,
        ]);

        $this->assertDatabaseHas('dishes', [
            'id' => $dish->id,
            'soda_id' => $this->soda->id,
            'category_id' => null,
        ]);
    }

    /** Missing and foreign categories answer the same 404. */
    public function test_missing_and_foreign_categories_are_not_found(): void
    {
        $foreign = CategoryModel::factory()->create([
            'name' => 'Extranjera',
        ]);

        $foreignResponse = $this->deleteJson(
            $this->endpoint($foreign->id),
        );

        $missingResponse = $this->deleteJson(
            $this->endpoint('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99'),
        );

        $foreignResponse->assertNotFound();

        $this->assertSame(
            Arr::except($missingResponse->json(), 'instance'),
            Arr::except($foreignResponse->json(), 'instance'),
        );

        $this->assertDatabaseHas('categories', [
            'id' => $foreign->id,
        ]);
    }

    /** Build the endpoint of a category. */
    private function endpoint(?string $categoryId = null): string
    {
        return '/api/v1/cocina/categorias/'.($categoryId ?? $this->category->id);
    }
}
