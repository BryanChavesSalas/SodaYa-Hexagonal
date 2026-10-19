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
        public private(set) Portions $basePortions,
        public private(set) ?CategoryId $categoryId,
        public private(set) bool $active,
        public private(set) ?string $photoPath,
    ) {}

    /** Register a new dish, active from the start; without base portions, the ones available today become the base. */
    public static function create(
        DishId $id,
        SodaId $sodaId,
        DishName $name,
        ?DishDescription $description,
        Price $price,
        PreparationTime $preparationTime,
        Portions $availablePortions,
        ?CategoryId $categoryId,
        ?Portions $basePortions = null,
    ): self {
        return new self($id, $sodaId, $name, $description, $price, $preparationTime, $availablePortions, $basePortions ?? $availablePortions, $categoryId, true, null);
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
        Portions $basePortions,
        ?CategoryId $categoryId,
        bool $active,
        ?string $photoPath = null,
    ): self {
        return new self($id, $sodaId, $name, $description, $price, $preparationTime, $availablePortions, $basePortions, $categoryId, $active, $photoPath);
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

    /** Change the portions prepared on a normal day; the portions left today stay as they are. */
    public function changeBasePortions(Portions $basePortions): void
    {
        $this->basePortions = $basePortions;
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

    /** Show a new photo and answer the path of the one it replaces, if any. */
    public function changePhoto(string $path): ?string
    {
        $previous = $this->photoPath;
        $this->photoPath = $path;

        return $previous;
    }

    /** Tell whether no portions are left to sell. */
    public function isSoldOut(): bool
    {
        return $this->availablePortions->quantity === 0;
    }
}
