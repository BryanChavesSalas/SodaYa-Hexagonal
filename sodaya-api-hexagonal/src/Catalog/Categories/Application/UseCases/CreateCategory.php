<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Application\UseCases;

use Src\Catalog\Categories\Application\DTOs\CreateCategoryCommand;
use Src\Catalog\Categories\Domain\Contracts\CategoryRepository;
use Src\Catalog\Categories\Domain\Entities\Category;
use Src\Catalog\Categories\Domain\ValueObjects\CategoryName;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class CreateCategory
{
    /** Receive the category repository port. */
    public function __construct(private CategoryRepository $categories) {}

    /** Register a new category for the soda. */
    public function execute(CreateCategoryCommand $command): Category
    {
        $category = Category::create(
            $this->categories->nextId(),
            new SodaId($command->sodaId),
            new CategoryName($command->name),
        );

        $this->categories->save($category);

        return $category;
    }
}
