<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Application\DTOs;

final readonly class PublicMenu
{
    /**
     * Describe the menu a soda offers to its visitors.
     *
     * @param  list<MenuCategory>  $categories
     * @param  bool  $abierta  Whether the soda is open at the moment of the response.
     */
    public function __construct(
        public string $sodaId,
        public string $sodaName,
        public array $categories,
        public bool $abierta,
    ) {}
}
