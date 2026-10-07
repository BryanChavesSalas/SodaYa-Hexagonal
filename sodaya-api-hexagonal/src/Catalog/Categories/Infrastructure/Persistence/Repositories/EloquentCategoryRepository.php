<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Catalog\Categories\Domain\Contracts\CategoryRepository;
use Src\Catalog\Categories\Domain\Entities\Category;
use Src\Catalog\Categories\Domain\Exceptions\CategoryNameAlreadyInUseException;
use Src\Catalog\Categories\Domain\ValueObjects\CategoryName;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class EloquentCategoryRepository implements CategoryRepository
{
    /** Generate a time-ordered UUID for a new category. */
    public function nextId(): CategoryId
    {
        return new CategoryId((string) Str::uuid7());
    }

    /** Insert or update the category inside a savepoint. */
    public function save(Category $category): void
    {
        try {
            DB::transaction(fn () => CategoryModel::query()->updateOrCreate(
                ['id' => $category->id->value],
                [
                    'soda_id' => $category->sodaId->value,
                    'name' => $category->name->value,
                ],
            ));
        } catch (UniqueConstraintViolationException) {
            throw CategoryNameAlreadyInUseException::create();
        }
    }

    /** Find the category scoped to its soda. */
    public function find(CategoryId $id, SodaId $sodaId): ?Category
    {
        $model = CategoryModel::query()
            ->where('soda_id', $sodaId->value)
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * List the categories of the soda ordered by name.
     *
     * @return list<Category>
     */
    public function allOf(SodaId $sodaId): array
    {
        $categories = CategoryModel::query()
            ->where('soda_id', $sodaId->value)
            ->orderBy('name')
            ->get();

        return array_values($categories->map($this->toDomain(...))->all());
    }

    /** Delete the persisted category. */
    public function delete(Category $category): void
    {
        CategoryModel::query()
            ->where('soda_id', $category->sodaId->value)
            ->whereKey($category->id->value)
            ->delete();
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(CategoryModel $model): Category
    {
        return Category::reconstitute(
            new CategoryId($model->id),
            new SodaId($model->soda_id),
            new CategoryName($model->name),
        );
    }
}
