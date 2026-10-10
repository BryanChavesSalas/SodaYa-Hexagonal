<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\DTOs;

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
    ) {}

    /** Return the same menu flagged as open or closed. */
    public function withOpenStatus(bool $open): self
    {
        return new self($this->sodaId, $this->sodaName, $this->categories, $open);
    }
}
