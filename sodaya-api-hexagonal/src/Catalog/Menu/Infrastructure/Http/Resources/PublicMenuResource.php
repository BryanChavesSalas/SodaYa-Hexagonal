<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Menu\Application\DTOs\PublicMenu;

/**
 * @property-read PublicMenu $resource
 */
#[SchemaName('MenuPublico')]
final class PublicMenuResource extends JsonResource
{
    /** Wrap the public menu of a soda. */
    public function __construct(private readonly PublicMenu $menu)
    {
        parent::__construct($menu);
    }

    /**
     * Expose the soda, whether it is open now and its dishes grouped by category.
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
            'abierta' => $this->menu->abierta,
            'categorias' => MenuCategoryResource::collection($this->menu->categories),
        ];
    }
}
