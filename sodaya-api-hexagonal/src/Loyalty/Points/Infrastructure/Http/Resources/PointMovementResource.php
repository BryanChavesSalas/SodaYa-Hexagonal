<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Loyalty\Points\Domain\Entities\PointMovement;
use Src\Loyalty\Points\Domain\ValueObjects\MovementReason;

#[SchemaName('MovimientoDePuntos')]
final class PointMovementResource extends JsonResource
{
    /** Wrap a movement of the ledger. */
    public function __construct(private readonly PointMovement $movement)
    {
        parent::__construct($movement);
    }

    /**
     * Expose the movement with the vocabulary of the business; the points are negative in a redemption.
     *
     * @return array{id: string, motivo: string, puntos: int, pedido_id: string|null, creado_en: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->movement->id->value,
            'motivo' => $this->movement->reason === MovementReason::Purchase ? 'compra' : 'canje',
            'puntos' => $this->movement->points,
            'pedido_id' => $this->movement->orderId?->value,
            'creado_en' => $this->movement->createdAt->format(DATE_ATOM),
        ];
    }
}
