<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Catalog\Dishes\Domain\Contracts\DishRepository;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\Exceptions\DishNameAlreadyInUseException;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Shared\Domain\ValueObjects\SodaId;

final class InMemoryDishRepository implements DishRepository
{
    /** @var array<string, Dish> */
    private array $dishes = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): DishId
    {
        return new DishId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store the dish enforcing a unique name per soda. */
    public function save(Dish $dish): void
    {
        foreach ($this->dishes as $stored) {
            $sameSoda = $stored->sodaId->equals($dish->sodaId);
            $sameName = $stored->name->value === $dish->name->value;

            if ($sameSoda && $sameName && ! $stored->id->equals($dish->id)) {
                throw DishNameAlreadyInUseException::create();
            }
        }

        $this->dishes[$dish->id->value] = $dish;
    }

    /** Find the dish scoped to its soda. */
    public function find(DishId $id, SodaId $sodaId): ?Dish
    {
        $dish = $this->dishes[$id->value] ?? null;

        return $dish?->sodaId->equals($sodaId) === true ? $dish : null;
    }

    /**
     * List the dishes of the soda ordered by name.
     *
     * @return list<Dish>
     */
    public function allOf(SodaId $sodaId): array
    {
        $dishes = array_filter($this->dishes, fn (Dish $dish): bool => $dish->sodaId->equals($sodaId));

        usort($dishes, fn (Dish $a, Dish $b): int => $a->name->value <=> $b->name->value);

        return $dishes;
    }
}
