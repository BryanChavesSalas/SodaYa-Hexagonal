<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Persistence\Queries;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Application\DTOs\MenuCategory;
use Src\Catalog\Menu\Application\DTOs\MenuDish;
use Src\Catalog\Menu\Application\DTOs\PublicMenu;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Src\Sodas\Schedule\Domain\ExceptionalClosure;
use Src\Sodas\Schedule\Domain\OpeningSchedule;
use Src\Sodas\Schedule\Domain\TimeSlot;

final readonly class EloquentPublicMenuReader implements PublicMenuReader
{
    /** Read the menu with one query for the soda and one for its dishes. */
    public function menuOf(SodaId $sodaId): ?PublicMenu
    {
        $soda = SodaModel::query()->find($sodaId->value);

        if ($soda === null) {
            return null;
        }

        $categories = $this->activeDishesOf($sodaId)
            ->with('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category_id')
            ->map($this->toCategory(...))
            ->sortBy(fn (MenuCategory $category): array => [$category->id === null, $category->name]);

        $isOpen = $this->scheduleOf($sodaId)->isOpenAt(now()->toDateTimeImmutable());

        return new PublicMenu($soda->id, $soda->name, array_values($categories->all()), $isOpen);
    }

    /** Read a single active dish of the soda. */
    public function dishOf(SodaId $sodaId, DishId $dishId): ?MenuDish
    {
        $dish = $this->activeDishesOf($sodaId)->find($dishId->value);

        return $dish === null ? null : $this->toDish($dish);
    }

    /** Build the opening schedule of the soda from its slots and exceptional closures. */
    private function scheduleOf(SodaId $sodaId): OpeningSchedule
    {
        $slotsByDay = [];

        foreach (DB::table('opening_slots')->where('soda_id', $sodaId->value)->get() as $row) {
            $slotsByDay[(int) $row->day_of_week][] = new TimeSlot((int) $row->opens_at, (int) $row->closes_at);
        }

        $closures = [];

        foreach (DB::table('exceptional_closures')->where('soda_id', $sodaId->value)->get() as $row) {
            $closures[] = new ExceptionalClosure(
                CarbonImmutable::parse($row->starts_at)->toDateTimeImmutable(),
                CarbonImmutable::parse($row->ends_at)->toDateTimeImmutable(),
            );
        }

        return new OpeningSchedule($slotsByDay, $closures);
    }

    /**
     * Scope the query to the dishes a visitor may see.
     *
     * @return Builder<DishModel>
     */
    private function activeDishesOf(SodaId $sodaId): Builder
    {
        return DishModel::query()
            ->where('soda_id', $sodaId->value)
            ->where('is_active', true);
    }

    /**
     * Build a category from the dishes that share it.
     *
     * @param  Collection<int, DishModel>  $dishes
     */
    private function toCategory(Collection $dishes): MenuCategory
    {
        $category = $dishes->firstOrFail()->category;

        return new MenuCategory(
            $category?->id,
            $category?->name,
            array_values($dishes->map($this->toDish(...))->all()),
        );
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
