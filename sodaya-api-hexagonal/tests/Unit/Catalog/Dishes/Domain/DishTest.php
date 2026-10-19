<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Dishes\Domain;

use PHPUnit\Framework\TestCase;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\ValueObjects\DishDescription;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Dishes\Domain\ValueObjects\DishName;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Catalog\Dishes\Domain\ValueObjects\PreparationTime;
use Src\Catalog\Dishes\Domain\ValueObjects\Price;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

final class DishTest extends TestCase
{
    /** A new dish starts active. */
    public function test_new_dish_is_active(): void
    {
        $this->assertTrue($this->dish()->active);
    }

    /** Deactivating keeps the dish and it can be activated again. */
    public function test_dish_can_be_deactivated_and_reactivated(): void
    {
        $dish = $this->dish();

        $dish->deactivate();
        $this->assertFalse($dish->active);

        $dish->activate();
        $this->assertTrue($dish->active);
    }

    /** A dish without portions is sold out. */
    public function test_dish_without_portions_is_sold_out(): void
    {
        $this->assertFalse($this->dish(portions: 1)->isSoldOut());
        $this->assertTrue($this->dish(portions: 0)->isSoldOut());
    }

    /** Revising replaces the editable details and keeps the identity. */
    public function test_revise_replaces_the_editable_details(): void
    {
        $dish = $this->dish();
        $categoryId = new CategoryId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d33');

        $dish->revise(
            new DishName('Casado de pescado'),
            new DishDescription('Con patacones'),
            new Price(3_500),
            new PreparationTime(20),
            new Portions(8),
            $categoryId,
        );

        $this->assertSame('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11', $dish->id->value);
        $this->assertSame('Casado de pescado', $dish->name->value);
        $this->assertSame('Con patacones', $dish->description?->value);
        $this->assertSame(3_500, $dish->price->amount);
        $this->assertSame(20, $dish->preparationTime->minutes);
        $this->assertSame(8, $dish->availablePortions->quantity);
        $this->assertTrue($categoryId->equals($dish->categoryId));
    }

    /** Without base portions, a new dish takes the ones available today as its base. */
    public function test_new_dish_takes_its_portions_as_the_base(): void
    {
        $this->assertSame(10, $this->dish()->basePortions->quantity);
    }

    /** Changing the base portions leaves the portions available today untouched. */
    public function test_changing_the_base_keeps_the_portions_of_today(): void
    {
        $dish = $this->dish(portions: 3);

        $dish->changeBasePortions(new Portions(25));

        $this->assertSame(25, $dish->basePortions->quantity);
        $this->assertSame(3, $dish->availablePortions->quantity);
    }

    /** Build a valid dish for the scenarios. */
    private function dish(int $portions = 10): Dish
    {
        return Dish::create(
            new DishId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d11'),
            new SodaId('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22'),
            new DishName('Casado de pollo'),
            null,
            new Price(2_800),
            new PreparationTime(15),
            new Portions($portions),
            null,
        );
    }
}
