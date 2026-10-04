<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\UseCases;

use Src\Catalog\Dishes\Application\DTOs\UpdateDishCommand;
use Src\Catalog\Dishes\Domain\Contracts\DishRepository;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\Exceptions\DishNotFoundException;
use Src\Catalog\Dishes\Domain\ValueObjects\DishDescription;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Dishes\Domain\ValueObjects\DishName;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Catalog\Dishes\Domain\ValueObjects\PreparationTime;
use Src\Catalog\Dishes\Domain\ValueObjects\Price;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class UpdateDish
{
    /** Receive the dish repository port. */
    public function __construct(private DishRepository $dishes) {}

    /** Apply the requested changes to a dish of the soda. */
    public function execute(UpdateDishCommand $command): Dish
    {
        $dish = $this->dishes->find(new DishId($command->dishId), new SodaId($command->sodaId))
            ?? throw DishNotFoundException::create();

        $changes = $command->changes;

        $dish->revise(
            isset($changes['name']) ? new DishName($changes['name']) : $dish->name,
            array_key_exists('description', $changes)
                ? $this->description($changes['description'])
                : $dish->description,
            isset($changes['price']) ? new Price($changes['price']) : $dish->price,
            isset($changes['preparation_minutes'])
                ? new PreparationTime($changes['preparation_minutes'])
                : $dish->preparationTime,
            isset($changes['available_portions'])
                ? new Portions($changes['available_portions'])
                : $dish->availablePortions,
            array_key_exists('category_id', $changes)
                ? $this->categoryId($changes['category_id'])
                : $dish->categoryId,
        );

        match ($changes['active'] ?? null) {
            true => $dish->activate(),
            false => $dish->deactivate(),
            null => null,
        };

        $this->dishes->save($dish);

        return $dish;
    }

    /** Build the optional description. */
    private function description(?string $value): ?DishDescription
    {
        return $value === null ? null : new DishDescription($value);
    }

    /** Build the optional category reference. */
    private function categoryId(?string $value): ?CategoryId
    {
        return $value === null ? null : new CategoryId($value);
    }
}
