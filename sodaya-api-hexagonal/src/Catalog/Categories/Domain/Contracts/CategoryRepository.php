<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Domain\Contracts;

use Src\Catalog\Categories\Domain\Entities\Category;
use Src\Catalog\Categories\Domain\Exceptions\CategoryNameAlreadyInUseException;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

interface CategoryRepository
{
    /** Generate the identity for a new category. */
    public function nextId(): CategoryId;

    /**
     * Persist a new or modified category.
     *
     * @throws CategoryNameAlreadyInUseException
     */
    public function save(Category $category): void;

    /** Find a category that belongs to the given soda. */
    public function find(CategoryId $id, SodaId $sodaId): ?Category;

    /**
     * List every category of a soda ordered by name.
     *
     * @return list<Category>
     */
    public function allOf(SodaId $sodaId): array;

    /** Remove a category that belongs to its soda. */
    public function delete(Category $category): void;
}
