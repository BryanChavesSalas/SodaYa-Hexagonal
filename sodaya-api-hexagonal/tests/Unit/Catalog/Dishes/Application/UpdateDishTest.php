<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Dishes\Application;

use PHPUnit\Framework\TestCase;
use Src\Catalog\Dishes\Application\DTOs\CreateDishCommand;
use Src\Catalog\Dishes\Application\DTOs\UpdateDishCommand;
use Src\Catalog\Dishes\Application\UseCases\CreateDish;
use Src\Catalog\Dishes\Application\UseCases\UpdateDish;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\Exceptions\DishNameAlreadyInUseException;
use Src\Catalog\Dishes\Domain\Exceptions\DishNotFoundException;
use Tests\Support\Catalog\InMemoryDishRepository;

final class UpdateDishTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d23';

    private InMemoryDishRepository $dishes;

    private UpdateDish $updateDish;

    /** Wire the use case to an in-memory repository. */
    protected function setUp(): void
    {
        $this->dishes = new InMemoryDishRepository;
        $this->updateDish = new UpdateDish($this->dishes);
    }

    /** Only the fields present in the command change. */
    public function test_changes_only_the_given_fields(): void
    {
        $dish = $this->existingDish('Casado de pollo');

        $updated = $this->updateDish->execute(
            new UpdateDishCommand($dish->id->value, self::SODA_ID, ['price' => 3_000]),
        );

        $this->assertSame(3_000, $updated->price->amount);
        $this->assertSame('Casado de pollo', $updated->name->value);
        $this->assertSame('Con ensalada', $updated->description?->value);
        $this->assertTrue($updated->active);
    }

    /** A null description clears it instead of being ignored. */
    public function test_null_clears_an_optional_field(): void
    {
        $dish = $this->existingDish('Casado de pollo');

        $updated = $this->updateDish->execute(
            new UpdateDishCommand($dish->id->value, self::SODA_ID, ['description' => null]),
        );

        $this->assertNull($updated->description);
    }

    /** The active flag deactivates and reactivates the dish. */
    public function test_toggles_the_active_state(): void
    {
        $dish = $this->existingDish('Casado de pollo');

        $this->updateDish->execute(new UpdateDishCommand($dish->id->value, self::SODA_ID, ['active' => false]));
        $this->assertFalse($dish->active);

        $this->updateDish->execute(new UpdateDishCommand($dish->id->value, self::SODA_ID, ['active' => true]));
        $this->assertTrue($dish->active);
    }

    /** A dish of another soda is reported as missing. */
    public function test_dish_of_another_soda_is_not_found(): void
    {
        $dish = $this->existingDish('Casado de pollo');

        $this->expectException(DishNotFoundException::class);

        $this->updateDish->execute(new UpdateDishCommand($dish->id->value, self::OTHER_SODA_ID, ['price' => 3_000]));
    }

    /** Renaming to a name used by another dish of the soda fails. */
    public function test_rejects_a_name_used_by_another_dish(): void
    {
        $this->existingDish('Casado de pollo');
        $dish = $this->existingDish('Casado de pescado');

        $this->expectException(DishNameAlreadyInUseException::class);

        $this->updateDish->execute(
            new UpdateDishCommand($dish->id->value, self::SODA_ID, ['name' => 'Casado de pollo']),
        );
    }

    /** Store a dish to update in the scenario soda. */
    private function existingDish(string $name): Dish
    {
        return new CreateDish($this->dishes)->execute(
            new CreateDishCommand(self::SODA_ID, $name, 'Con ensalada', 2_800, 15, 10, null),
        );
    }
}
