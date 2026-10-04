<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Dishes;

use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\Exceptions\DishNameAlreadyInUseException;
use Src\Catalog\Dishes\Domain\ValueObjects\DishDescription;
use Src\Catalog\Dishes\Domain\ValueObjects\DishName;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Catalog\Dishes\Domain\ValueObjects\PreparationTime;
use Src\Catalog\Dishes\Domain\ValueObjects\Price;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Repositories\EloquentDishRepository;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class EloquentDishRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private EloquentDishRepository $repository;

    private SodaId $sodaId;

    /** Create the repository and the soda every scenario works on. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentDishRepository;
        $this->sodaId = new SodaId(SodaModel::factory()->create()->id);
    }

    /** A saved dish is read back with the same state. */
    public function test_saved_dish_is_read_back_unchanged(): void
    {
        $category = CategoryModel::factory()->create(['soda_id' => $this->sodaId->value]);
        $dish = $this->dish('Casado de pollo', new CategoryId($category->id));

        $this->repository->save($dish);

        $this->assertEquals($dish, $this->repository->find($dish->id, $this->sodaId));
    }

    /** Saving an existing dish updates it instead of duplicating it. */
    public function test_saving_an_existing_dish_updates_it(): void
    {
        $dish = $this->dish('Casado de pollo');
        $this->repository->save($dish);

        $dish->deactivate();
        $this->repository->save($dish);

        $this->assertDatabaseCount('dishes', 1);
        $this->assertFalse($this->repository->find($dish->id, $this->sodaId)?->active);
    }

    /** A dish is invisible when searched from another soda. */
    public function test_dish_of_another_soda_is_not_found(): void
    {
        $dish = $this->dish('Casado de pollo');
        $this->repository->save($dish);

        $otherSodaId = new SodaId(SodaModel::factory()->create()->id);

        $this->assertNull($this->repository->find($dish->id, $otherSodaId));
    }

    /** The listing is ordered by name and limited to the soda. */
    public function test_listing_is_sorted_and_scoped_to_the_soda(): void
    {
        $this->repository->save($this->dish('Olla de carne'));
        $this->repository->save($this->dish('Arroz con pollo'));
        DishModel::factory()->create();

        $names = array_map(fn (Dish $dish): string => $dish->name->value, $this->repository->allOf($this->sodaId));

        $this->assertSame(['Arroz con pollo', 'Olla de carne'], $names);
    }

    /** A repeated name raises a domain error and keeps the transaction usable. */
    public function test_repeated_name_raises_a_domain_error(): void
    {
        $this->repository->save($this->dish('Casado de pollo'));

        try {
            $this->repository->save($this->dish('Casado de pollo'));
            $this->fail('A repeated dish name was accepted.');
        } catch (DishNameAlreadyInUseException) {
            $this->assertDatabaseCount('dishes', 1);
        }
    }

    /** Build a dish of the scenario soda with a fresh identity. */
    private function dish(string $name, ?CategoryId $categoryId = null): Dish
    {
        return Dish::create(
            $this->repository->nextId(),
            $this->sodaId,
            new DishName($name),
            new DishDescription('Con arroz, frijoles y ensalada'),
            new Price(2_800),
            new PreparationTime(15),
            new Portions(10),
            $categoryId,
        );
    }
}
