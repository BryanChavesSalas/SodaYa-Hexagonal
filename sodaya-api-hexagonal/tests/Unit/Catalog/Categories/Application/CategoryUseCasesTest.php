<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog\Categories\Application;

use PHPUnit\Framework\TestCase;
use Src\Catalog\Categories\Application\DTOs\CreateCategoryCommand;
use Src\Catalog\Categories\Application\DTOs\RenameCategoryCommand;
use Src\Catalog\Categories\Application\UseCases\CreateCategory;
use Src\Catalog\Categories\Application\UseCases\DeleteCategory;
use Src\Catalog\Categories\Application\UseCases\ListCategories;
use Src\Catalog\Categories\Application\UseCases\RenameCategory;
use Src\Catalog\Categories\Domain\Exceptions\CategoryNameAlreadyInUseException;
use Src\Catalog\Categories\Domain\Exceptions\CategoryNotFoundException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Catalog\InMemoryCategoryRepository;

final class CategoryUseCasesTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d33';

    private InMemoryCategoryRepository $categories;

    /** Create an empty repository before every scenario. */
    protected function setUp(): void
    {
        $this->categories = new InMemoryCategoryRepository;
    }

    /** Creating stores a category for the requested soda. */
    public function test_create_category_stores_it(): void
    {
        $useCase = new CreateCategory($this->categories);

        $category = $useCase->execute(
            new CreateCategoryCommand(self::SODA_ID, 'Bebidas'),
        );

        $stored = $this->categories->find(
            $category->id,
            new SodaId(self::SODA_ID),
        );

        $this->assertSame($category, $stored);
        $this->assertSame('Bebidas', $category->name->value);
    }

    /** Categories are listed only for their soda and ordered by name. */
    public function test_list_categories_returns_only_the_soda_categories(): void
    {
        $create = new CreateCategory($this->categories);
        $list = new ListCategories($this->categories);

        $create->execute(
            new CreateCategoryCommand(self::SODA_ID, 'Postres'),
        );

        $create->execute(
            new CreateCategoryCommand(self::SODA_ID, 'Bebidas'),
        );

        $create->execute(
            new CreateCategoryCommand(self::OTHER_SODA_ID, 'Extranjera'),
        );

        $categories = $list->execute(self::SODA_ID);

        $names = array_map(
            fn ($category): string => $category->name->value,
            $categories,
        );

        $this->assertSame(['Bebidas', 'Postres'], $names);
    }

    /** Renaming changes the category name. */
    public function test_rename_category_changes_its_name(): void
    {
        $create = new CreateCategory($this->categories);
        $rename = new RenameCategory($this->categories);

        $category = $create->execute(
            new CreateCategoryCommand(self::SODA_ID, 'Bebidas'),
        );

        $renamed = $rename->execute(
            new RenameCategoryCommand(
                $category->id->value,
                self::SODA_ID,
                'Bebidas frías',
            ),
        );

        $this->assertSame('Bebidas frías', $renamed->name->value);
    }

    /** Renaming a category from another soda is reported as not found. */
    public function test_rename_category_of_another_soda_is_not_found(): void
    {
        $create = new CreateCategory($this->categories);
        $rename = new RenameCategory($this->categories);

        $category = $create->execute(
            new CreateCategoryCommand(self::SODA_ID, 'Bebidas'),
        );

        $this->expectException(CategoryNotFoundException::class);

        $rename->execute(
            new RenameCategoryCommand(
                $category->id->value,
                self::OTHER_SODA_ID,
                'Otro nombre',
            ),
        );
    }

    /** A repeated category name in the same soda is rejected. */
    public function test_repeated_name_is_rejected(): void
    {
        $create = new CreateCategory($this->categories);

        $create->execute(
            new CreateCategoryCommand(self::SODA_ID, 'Bebidas'),
        );

        $this->expectException(CategoryNameAlreadyInUseException::class);

        $create->execute(
            new CreateCategoryCommand(self::SODA_ID, 'Bebidas'),
        );
    }

    /** Deleting removes the category from the repository. */
    public function test_delete_category_removes_it(): void
    {
        $create = new CreateCategory($this->categories);
        $delete = new DeleteCategory($this->categories);

        $category = $create->execute(
            new CreateCategoryCommand(self::SODA_ID, 'Bebidas'),
        );

        $delete->execute(
            $category->id->value,
            self::SODA_ID,
        );

        $this->assertNull(
            $this->categories->find(
                $category->id,
                new SodaId(self::SODA_ID),
            ),
        );
    }

    /** Deleting a category from another soda is reported as not found. */
    public function test_delete_category_of_another_soda_is_not_found(): void
    {
        $create = new CreateCategory($this->categories);
        $delete = new DeleteCategory($this->categories);

        $category = $create->execute(
            new CreateCategoryCommand(self::SODA_ID, 'Bebidas'),
        );

        $this->expectException(CategoryNotFoundException::class);

        $delete->execute(
            $category->id->value,
            self::OTHER_SODA_ID,
        );
    }
}
