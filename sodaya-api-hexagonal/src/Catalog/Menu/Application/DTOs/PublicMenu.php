<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\DTOs;

/**
 * @phpstan-import-type MenuCategoryRow from MenuCategory
 *
 * @phpstan-type PublicMenuRows array{soda_id: string, soda_name: string, soda_logo_path: string|null, categories: list<MenuCategoryRow>}
 */
final readonly class PublicMenu
{
    /**
     * Describe the menu a soda offers to its visitors.
     *
     * @param  list<MenuCategory>  $categories
     */
    public function __construct(
        public string $sodaId,
        public string $sodaName,
        public array $categories,
        public bool $open = false,
        public ?string $sodaLogoPath = null,
    ) {}

    /**
     * Rebuild the menu from its cached rows with the portions each dish has right now.
     *
     * @param  PublicMenuRows  $rows
     * @param  array<string, int>  $availablePortions  by dish identifier
     */
    public static function fromArray(array $rows, array $availablePortions): self
    {
        return new self(
            $rows['soda_id'],
            $rows['soda_name'],
            array_map(
                fn (array $category): MenuCategory => MenuCategory::fromArray($category, $availablePortions),
                $rows['categories'],
            ),
            sodaLogoPath: $rows['soda_logo_path'],
        );
    }

    /** Return the same menu flagged as open or closed. */
    public function withOpenStatus(bool $open): self
    {
        return new self($this->sodaId, $this->sodaName, $this->categories, $open, $this->sodaLogoPath);
    }

    /** Return the same menu with the happy hour taken off every dish: the current price is decided on every response. */
    public function withDiscount(int $percentage): self
    {
        return new self(
            $this->sodaId,
            $this->sodaName,
            array_map(fn (MenuCategory $category): MenuCategory => $category->withDiscount($percentage), $this->categories),
            $this->open,
            $this->sodaLogoPath,
        );
    }

    /**
     * Flatten what only changes when the owner edits, as plain arrays a cache can keep: the open flag is
     * decided on every response and stays out.
     *
     * @return PublicMenuRows
     */
    public function toArray(): array
    {
        return [
            'soda_id' => $this->sodaId,
            'soda_name' => $this->sodaName,
            'soda_logo_path' => $this->sodaLogoPath,
            'categories' => array_map(fn (MenuCategory $category): array => $category->toArray(), $this->categories),
        ];
    }
}
