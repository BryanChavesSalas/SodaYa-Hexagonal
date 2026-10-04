<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Catalog\Dishes\Domain\Contracts\DishRepository;
use Src\Catalog\Dishes\Domain\Entities\Dish;
use Src\Catalog\Dishes\Domain\Exceptions\DishNameAlreadyInUseException;
use Src\Catalog\Dishes\Domain\ValueObjects\DishDescription;
use Src\Catalog\Dishes\Domain\ValueObjects\DishId;
use Src\Catalog\Dishes\Domain\ValueObjects\DishName;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Catalog\Dishes\Domain\ValueObjects\PreparationTime;
use Src\Catalog\Dishes\Domain\ValueObjects\Price;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class EloquentDishRepository implements DishRepository
{
    /** Generate a time-ordered UUID for a new dish. */
    public function nextId(): DishId
    {
        return new DishId((string) Str::uuid7());
    }

    /** Insert or update the dish inside a savepoint. */
    public function save(Dish $dish): void
    {
        try {
            DB::transaction(fn () => DishModel::query()->updateOrCreate(
                ['id' => $dish->id->value],
                [
                    'soda_id' => $dish->sodaId->value,
                    'category_id' => $dish->categoryId?->value,
                    'name' => $dish->name->value,
                    'description' => $dish->description?->value,
                    'price' => $dish->price->amount,
                    'preparation_minutes' => $dish->preparationTime->minutes,
                    'available_portions' => $dish->availablePortions->quantity,
                    'is_active' => $dish->active,
                ],
            ));
        } catch (UniqueConstraintViolationException) {
            throw DishNameAlreadyInUseException::create();
        }
    }

    /** Find the dish scoped to its soda. */
    public function find(DishId $id, SodaId $sodaId): ?Dish
    {
        $model = DishModel::query()
            ->where('soda_id', $sodaId->value)
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * List the dishes of the soda ordered by name.
     *
     * @return list<Dish>
     */
    public function allOf(SodaId $sodaId): array
    {
        $dishes = DishModel::query()
            ->where('soda_id', $sodaId->value)
            ->orderBy('name')
            ->get();

        return array_values($dishes->map($this->toDomain(...))->all());
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(DishModel $model): Dish
    {
        return Dish::reconstitute(
            new DishId($model->id),
            new SodaId($model->soda_id),
            new DishName($model->name),
            $model->description === null ? null : new DishDescription($model->description),
            new Price($model->price),
            new PreparationTime($model->preparation_minutes),
            new Portions($model->available_portions),
            $model->category_id === null ? null : new CategoryId($model->category_id),
            $model->is_active,
        );
    }
}
