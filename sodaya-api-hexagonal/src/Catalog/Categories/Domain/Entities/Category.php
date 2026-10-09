<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Domain\Entities;

use Src\Catalog\Categories\Domain\ValueObjects\CategoryName;
use Src\Catalog\Shared\Domain\ValueObjects\CategoryId;
use Src\Shared\Domain\ValueObjects\SodaId;

final class Category
{
    /** Hold the full state of a category. */
    private function __construct(
        public readonly CategoryId $id,
        public readonly SodaId $sodaId,
        public private(set) CategoryName $name,
    ) {}

    /** Register a new category. */
    public static function create(
        CategoryId $id,
        SodaId $sodaId,
        CategoryName $name,
    ): self {
        return new self($id, $sodaId, $name);
    }

    /** Rebuild a category from its stored state. */
    public static function reconstitute(
        CategoryId $id,
        SodaId $sodaId,
        CategoryName $name,
    ): self {
        return new self($id, $sodaId, $name);
    }

    /** Rename the category. */
    public function rename(CategoryName $name): void
    {
        $this->name = $name;
    }
}
