<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\DTOs;

final readonly class MenuCategory
{
    /**
     * Group dishes under a category; a null identity holds the uncategorized ones.
     *
     * @param  list<MenuDish>  $dishes
     */
    public function __construct(
        public ?string $id,
        public ?string $name,
        public array $dishes,
    ) {}
}
