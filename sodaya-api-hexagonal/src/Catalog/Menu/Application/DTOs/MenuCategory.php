<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\DTOs;

/**
 * @phpstan-import-type MenuDishRow from MenuDish
 *
 * @phpstan-type MenuCategoryRow array{id: string|null, name: string|null, dishes: list<MenuDishRow>}
 */
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

    /**
     * Rebuild the category from its cached row, giving each dish its live portions.
     *
     * @param  MenuCategoryRow  $row
     * @param  array<string, int>  $availablePortions  by dish identifier
     */
    public static function fromArray(array $row, array $availablePortions): self
    {
        return new self(
            $row['id'],
            $row['name'],
            array_map(
                fn (array $dish): MenuDish => MenuDish::fromArray($dish, $availablePortions[$dish['id']] ?? 0),
                $row['dishes'],
            ),
        );
    }

    /** Return the same category with the happy hour taken off every dish. */
    public function withDiscount(int $percentage): self
    {
        return new self(
            $this->id,
            $this->name,
            array_map(fn (MenuDish $dish): MenuDish => $dish->withDiscount($percentage), $this->dishes),
        );
    }

    /**
     * Flatten the category and its dishes.
     *
     * @return MenuCategoryRow
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'dishes' => array_map(fn (MenuDish $dish): array => $dish->toArray(), $this->dishes),
        ];
    }
}
