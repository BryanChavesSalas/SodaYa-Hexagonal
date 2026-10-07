<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Application\UseCases;

use Src\Catalog\Categories\Domain\Contracts\CategoryRepository;
use Src\Catalog\Categories\Domain\Entities\Category;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class ListCategories
{
    /** Receive the category repository port. */
    public function __construct(private CategoryRepository $categories) {}

    /**
     * List every category of the soda.
     *
     * @return list<Category>
     */
    public function execute(string $sodaId): array
    {
        return $this->categories->allOf(new SodaId($sodaId));
    }
}
