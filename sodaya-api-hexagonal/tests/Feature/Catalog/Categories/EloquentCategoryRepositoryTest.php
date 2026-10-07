<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Categories;

use Src\Catalog\Categories\Domain\Entities\Category;
use Src\Catalog\Categories\Domain\Exceptions\CategoryNameAlreadyInUseException;
use Src\Catalog\Categories\Domain\ValueObjects\CategoryName;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Categories\Infrastructure\Persistence\Repositories\EloquentCategoryRepository;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class EloquentCategoryRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private EloquentCategoryRepository $repository;

    private SodaId $sodaId;

    /** Create the repository and soda used by every scenario. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentCategoryRepository;
        $this->sodaId = new SodaId(
            SodaModel::factory()->create()->id,
        );
    }

    /** A saved category is read back with the same state. */
    public function test_saved_category_is_read_back_unchanged(): void
    {
        $category = $this->category('Bebidas');

        $this->repository->save($category);

        $this->assertEquals(
            $category,
            $this->repository->find($category->id, $this->sodaId),
        );
    }

    /** Saving an existing category updates it instead of duplicating it. */
    public function test_saving_an_existing_category_updates_it(): void
    {
        $category = $this->category('Bebidas');

        $this->repository->save($category);

        $category->rename(new CategoryName('Bebidas frías'));

        $this->repository->save($category);

        $this->assertDatabaseCount('categories', 1);

        $this->assertSame(
            'Bebidas frías',
            $this->repository
                ->find($category->id, $this->sodaId)
                ?->name
                ->value,
        );
    }

    /** A category is invisible when searched from another soda. */
    public function test_category_of_another_soda_is_not_found(): void
    {
        $category = $this->category('Bebidas');

        $this->repository->save($category);

        $otherSodaId = new SodaId(
            SodaModel::factory()->create()->id,
        );

        $this->assertNull(
            $this->repository->find(
                $category->id,
                $otherSodaId,
            ),
        );
    }

    /** The listing is ordered by name and limited to the soda. */
    public function test_listing_is_sorted_and_scoped_to_the_soda(): void
    {
        $this->repository->save($this->category('Postres'));
        $this->repository->save($this->category('Bebidas'));

        CategoryModel::factory()->create([
            'name' => 'Extranjera',
        ]);

        $names = array_map(
            fn (Category $category): string => $category->name->value,
            $this->repository->allOf($this->sodaId),
        );

        $this->assertSame(
            ['Bebidas', 'Postres'],
            $names,
        );
    }

    /** A repeated name raises a domain error. */
    public function test_repeated_name_raises_a_domain_error(): void
    {
        $this->repository->save(
            $this->category('Bebidas'),
        );

        try {
            $this->repository->save(
                $this->category('Bebidas'),
            );

            $this->fail(
                'A repeated category name was accepted.',
            );
        } catch (CategoryNameAlreadyInUseException) {
            $this->assertDatabaseCount('categories', 1);
        }
    }

    /** Deleting removes the category from persistence. */
    public function test_category_can_be_deleted(): void
    {
        $category = $this->category('Bebidas');

        $this->repository->save($category);
        $this->repository->delete($category);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id->value,
        ]);
    }

    /** Build a category of the scenario soda with a fresh identity. */
    private function category(string $name): Category
    {
        return Category::create(
            $this->repository->nextId(),
            $this->sodaId,
            new CategoryName($name),
        );
    }
}
