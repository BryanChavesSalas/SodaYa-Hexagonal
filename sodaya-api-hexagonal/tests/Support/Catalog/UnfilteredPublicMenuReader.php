<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Application\DTOs\MenuCategory;
use Src\Catalog\Menu\Application\DTOs\MenuDish;
use Src\Catalog\Menu\Application\DTOs\PublicMenu;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

final readonly class UnfilteredPublicMenuReader implements PublicMenuReader
{
    /** Read the soda and every dish the database lets through, as if the filter by soda had been forgotten. */
    public function menuOf(SodaId $sodaId): ?PublicMenu
    {
        $soda = SodaModel::query()->find($sodaId->value);

        if ($soda === null) {
            return null;
        }

        $dishes = DishModel::query()->orderBy('name')->get()->map($this->toDish(...));

        return new PublicMenu($soda->id, $soda->name, [new MenuCategory(null, null, array_values($dishes->all()))]);
    }

    /** Read a dish by its identifier alone, as if the filter by soda had been forgotten. */
    public function dishOf(SodaId $sodaId, DishId $dishId): ?MenuDish
    {
        $dish = DishModel::query()->find($dishId->value);

        return $dish === null ? null : $this->toDish($dish);
    }

    /** Build the visitor view of a dish. */
    private function toDish(DishModel $dish): MenuDish
    {
        return new MenuDish(
            $dish->id,
            $dish->name,
            $dish->description,
            $dish->price,
            $dish->preparation_minutes,
            $dish->available_portions,
            $dish->updated_at->toDateTimeImmutable(),
        );
    }
}
