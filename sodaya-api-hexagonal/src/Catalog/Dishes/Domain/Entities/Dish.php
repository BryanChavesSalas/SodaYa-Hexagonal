<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Domain\Entities;

use Src\Catalog\Dishes\Domain\ValueObjects\DishDescription;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Dishes\Domain\ValueObjects\DishName;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Catalog\Dishes\Domain\ValueObjects\PreparationTime;
use Src\Catalog\Dishes\Domain\ValueObjects\Price;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

final class Dish
{
    /** Hold the full state of a dish. */
    private function __construct(
        public readonly DishId $id,
        public readonly SodaId $sodaId,
        public private(set) DishName $name,
        public private(set) ?DishDescription $description,
        public private(set) Price $price,
        public private(set) PreparationTime $preparationTime,
        public private(set) Portions $availablePortions,
        public private(set) ?CategoryId $categoryId,
        public private(set) bool $active,
    ) {}

    /** Register a new dish, active from the start. */
    public static function create(
        DishId $id,
        SodaId $sodaId,
        DishName $name,
        ?DishDescription $description,
        Price $price,
        PreparationTime $preparationTime,
        Portions $availablePortions,
        ?CategoryId $categoryId,
    ): self {
        return new self($id, $sodaId, $name, $description, $price, $preparationTime, $availablePortions, $categoryId, true);
    }

    /** Rebuild a dish from its stored state. */
    public static function reconstitute(
        DishId $id,
        SodaId $sodaId,
        DishName $name,
        ?DishDescription $description,
        Price $price,
        PreparationTime $preparationTime,
        Portions $availablePortions,
        ?CategoryId $categoryId,
        bool $active,
    ): self {
        return new self($id, $sodaId, $name, $description, $price, $preparationTime, $availablePortions, $categoryId, $active);
    }

    /** Replace the editable details of the dish. */
    public function revise(
        DishName $name,
        ?DishDescription $description,
        Price $price,
        PreparationTime $preparationTime,
        Portions $availablePortions,
        ?CategoryId $categoryId,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->preparationTime = $preparationTime;
        $this->availablePortions = $availablePortions;
        $this->categoryId = $categoryId;
    }

    /** Put the dish back on the menu. */
    public function activate(): void
    {
        $this->active = true;
    }

    /** Take the dish off the menu without deleting it. */
    public function deactivate(): void
    {
        $this->active = false;
    }

    /** Tell whether no portions are left to sell. */
    public function isSoldOut(): bool
    {
        return $this->availablePortions->quantity === 0;
    }
}
