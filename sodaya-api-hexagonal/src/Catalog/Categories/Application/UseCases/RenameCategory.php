<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Application\UseCases;

use Src\Catalog\Categories\Application\DTOs\RenameCategoryCommand;
use Src\Catalog\Categories\Domain\Contracts\CategoryRepository;
use Src\Catalog\Categories\Domain\Entities\Category;
use Src\Catalog\Categories\Domain\Exceptions\CategoryNotFoundException;
use Src\Catalog\Categories\Domain\ValueObjects\CategoryName;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class RenameCategory
{
    /** Receive the category repository port. */
    public function __construct(private CategoryRepository $categories) {}

    /** Rename a category that belongs to the soda. */
    public function execute(RenameCategoryCommand $command): Category
    {
        $category = $this->categories->find(
            new CategoryId($command->categoryId),
            new SodaId($command->sodaId),
        ) ?? throw CategoryNotFoundException::create();

        $category->rename(new CategoryName($command->name));

        $this->categories->save($category);

        return $category;
    }
}
