<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Application\UseCases;

use Src\Catalog\Categories\Domain\Contracts\CategoryRepository;
use Src\Catalog\Categories\Domain\Exceptions\CategoryNotFoundException;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class DeleteCategory
{
    /** Receive the category repository port. */
    public function __construct(private CategoryRepository $categories) {}

    /** Remove a category that belongs to the soda. */
    public function execute(string $categoryId, string $sodaId): void
    {
        $category = $this->categories->find(
            new CategoryId($categoryId),
            new SodaId($sodaId),
        ) ?? throw CategoryNotFoundException::create();

        $this->categories->delete($category);
    }
}
