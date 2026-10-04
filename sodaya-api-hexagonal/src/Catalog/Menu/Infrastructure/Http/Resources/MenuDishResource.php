<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure\Http\Resources;

use DateTimeInterface;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Menu\Application\DTOs\MenuDish;

/**
 * @property-read MenuDish $resource
 */
#[SchemaName('PlatoDelMenu')]
final class MenuDishResource extends JsonResource
{
    /** Wrap the visitor view of a dish. */
    public function __construct(private readonly MenuDish $dish)
    {
        parent::__construct($dish);
    }

    /**
     * Expose the dish as it appears on the public menu.
     *
     * @return array<string, bool|int|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->dish->id,
            'nombre' => $this->dish->name,
            'descripcion' => $this->dish->description,
            'precio' => $this->dish->currentPrice,
            'minutos_preparacion' => $this->dish->preparationMinutes,
            'porciones_disponibles' => $this->dish->availablePortions,
            'agotado' => $this->dish->isSoldOut(),
            'actualizado_en' => $this->dish->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
