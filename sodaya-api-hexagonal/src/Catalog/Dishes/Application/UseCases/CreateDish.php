<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\UseCases;

use Src\Catalog\Dishes\Application\DTOs\CreateDishCommand;
use Src\Catalog\Dishes\Domain\Contracts\DishRepository;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\ValueObjects\DishDescription;
use Src\Catalog\Dishes\Domain\ValueObjects\DishName;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Catalog\Dishes\Domain\ValueObjects\PreparationTime;
use Src\Catalog\Dishes\Domain\ValueObjects\Price;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class CreateDish
{
    /** Receive the dish repository port. */
    public function __construct(private DishRepository $dishes) {}

    /** Register a new active dish for the soda. */
    public function execute(CreateDishCommand $command): Dish
    {
        $dish = Dish::create(
            $this->dishes->nextId(),
            new SodaId($command->sodaId),
            new DishName($command->name),
            $command->description === null ? null : new DishDescription($command->description),
            new Price($command->price),
            new PreparationTime($command->preparationMinutes),
            new Portions($command->availablePortions),
            $command->categoryId === null ? null : new CategoryId($command->categoryId),
        );

        $this->dishes->save($dish);

        return $dish;
    }
}
