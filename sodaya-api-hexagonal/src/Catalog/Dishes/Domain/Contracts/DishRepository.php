<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Domain\Contracts;

use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\Exceptions\DishNameAlreadyInUseException;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Shared\Domain\ValueObjects\SodaId;

interface DishRepository
{
    /** Generate the identity for a new dish. */
    public function nextId(): DishId;

    /**
     * Persist a new or modified dish.
     *
     * @throws DishNameAlreadyInUseException
     */
    public function save(Dish $dish): void;

    /** Find a dish that belongs to the given soda. */
    public function find(DishId $id, SodaId $sodaId): ?Dish;

    /**
     * List every dish of a soda ordered by name.
     *
     * @return list<Dish>
     */
    public function allOf(SodaId $sodaId): array;
}
