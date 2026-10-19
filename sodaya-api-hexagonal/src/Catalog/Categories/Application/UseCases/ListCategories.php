<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Application\UseCases;

use Src\Catalog\Categories\Domain\Contracts\CategoryRepository;
use Src\Catalog\Categories\Domain\Entities\Category;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ListCategories
{
    /** Receive the category repository port. */
    public function __construct(private CategoryRepository $categories) {}

    /**
     * List one page of the categories of the soda.
     *
     * @return Page<Category>
     */
    public function execute(string $sodaId, PageRequest $page): Page
    {
        return $this->categories->pageOf(new SodaId($sodaId), $page);
    }
}
