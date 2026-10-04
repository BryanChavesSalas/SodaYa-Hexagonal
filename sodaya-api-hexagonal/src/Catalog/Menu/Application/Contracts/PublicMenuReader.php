<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\Contracts;

use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Menu\Application\DTOs\MenuDish;
use Src\Catalog\Menu\Application\DTOs\PublicMenu;
use Src\Shared\Domain\ValueObjects\SodaId;

interface PublicMenuReader
{
    /** Read the active dishes of a soda grouped by category. */
    public function menuOf(SodaId $sodaId): ?PublicMenu;

    /** Read one active dish of a soda. */
    public function dishOf(SodaId $sodaId, DishId $dishId): ?MenuDish;
}
