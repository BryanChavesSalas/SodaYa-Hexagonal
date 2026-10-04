<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Menu\Application\DTOs\MenuCategory;

/**
 * @property-read MenuCategory $resource
 */
#[SchemaName('CategoriaDelMenu')]
final class MenuCategoryResource extends JsonResource
{
    /** Wrap a category of the public menu. */
    public function __construct(private readonly MenuCategory $category)
    {
        parent::__construct($category);
    }

    /**
     * Expose the category with its dishes.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->category->id,
            'nombre' => $this->category->name ?? __('catalog.uncategorized'),
            'platos' => MenuDishResource::collection($this->category->dishes),
        ];
    }
}
