<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\UseCases;

use Src\Catalog\Dishes\Domain\Contracts\DishRepository;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ListDishes
{
    /** Receive the dish repository port. */
    public function __construct(private DishRepository $dishes) {}

    /**
     * List every dish of the soda, active or not.
     *
     * @return list<Dish>
     */
    public function execute(string $sodaId): array
    {
        return $this->dishes->allOf(new SodaId($sodaId));
    }
}
