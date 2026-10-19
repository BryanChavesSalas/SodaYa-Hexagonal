<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Application\UseCases;

use Src\Catalog\Dishes\Domain\Contracts\DishRepository;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ListDishes
{
    /** Receive the dish repository port. */
    public function __construct(private DishRepository $dishes) {}

    /**
     * List one page of the dishes of the soda, active or not.
     *
     * @return Page<Dish>
     */
    public function execute(string $sodaId, PageRequest $page): Page
    {
        return $this->dishes->pageOf(new SodaId($sodaId), $page);
    }
}
