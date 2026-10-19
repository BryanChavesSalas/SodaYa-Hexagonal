<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Catalog\Categories\Domain\Contracts\CategoryRepository;
use Src\Catalog\Categories\Domain\Entities\Category;
use Src\Catalog\Categories\Domain\Exceptions\CategoryNameAlreadyInUseException;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Shared\Pages;

final class InMemoryCategoryRepository implements CategoryRepository
{
    /** @var array<string, Category> */
    private array $categories = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): CategoryId
    {
        return new CategoryId(
            sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence),
        );
    }

    /** Store the category enforcing a unique name per soda. */
    public function save(Category $category): void
    {
        foreach ($this->categories as $stored) {
            $sameSoda = $stored->sodaId->equals($category->sodaId);
            $sameName = $stored->name->value === $category->name->value;

            if ($sameSoda && $sameName && ! $stored->id->equals($category->id)) {
                throw CategoryNameAlreadyInUseException::create();
            }
        }

        $this->categories[$category->id->value] = $category;
    }

    /** Find the category scoped to its soda. */
    public function find(CategoryId $id, SodaId $sodaId): ?Category
    {
        $category = $this->categories[$id->value] ?? null;

        return $category?->sodaId->equals($sodaId) === true
            ? $category
            : null;
    }

    /**
     * List one page of the categories of the soda ordered by name.
     *
     * @return Page<Category>
     */
    public function pageOf(SodaId $sodaId, PageRequest $page): Page
    {
        $categories = array_filter(
            $this->categories,
            fn (Category $category): bool => $category->sodaId->equals($sodaId),
        );

        usort(
            $categories,
            fn (Category $a, Category $b): int => $a->name->value <=> $b->name->value,
        );

        return Pages::slice($categories, $page);
    }

    /** Remove a category. */
    public function delete(Category $category): void
    {
        unset($this->categories[$category->id->value]);
    }
}
