<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Dishes\Domain\Entities\Dish;

final class DishResource extends JsonResource
{
    /** Wrap a dish aggregate. */
    public function __construct(private readonly Dish $dish)
    {
        parent::__construct($dish);
    }

    /**
     * Expose the dish with the vocabulary of the business.
     *
     * @return array<string, bool|int|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->dish->id->value,
            'nombre' => $this->dish->name->value,
            'descripcion' => $this->dish->description?->value,
            'precio' => $this->dish->price->amount,
            'minutos_preparacion' => $this->dish->preparationTime->minutes,
            'porciones_disponibles' => $this->dish->availablePortions->quantity,
            'categoria_id' => $this->dish->categoryId?->value,
            'activo' => $this->dish->active,
            'agotado' => $this->dish->isSoldOut(),
        ];
    }
}
