<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Menu\Application\DTOs\MenuCategory;
use Src\Catalog\Menu\Application\DTOs\PublicMenu;

final class PublicMenuResource extends JsonResource
{
    /** Wrap the public menu of a soda. */
    public function __construct(private readonly PublicMenu $menu)
    {
        parent::__construct($menu);
    }

    /**
     * Expose the soda and its dishes grouped by category.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'soda' => [
                'id' => $this->menu->sodaId,
                'nombre' => $this->menu->sodaName,
            ],
            'categorias' => array_map(
                fn (MenuCategory $category): array => [
                    'id' => $category->id,
                    'nombre' => $category->name ?? __('catalog.uncategorized'),
                    'platos' => MenuDishResource::collection($category->dishes),
                ],
                $this->menu->categories,
            ),
        ];
    }
}
