<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Loyalty\Points\Application\DTOs\PointsStatement;
use Src\Loyalty\Points\Domain\Entities\PointMovement;

#[SchemaName('Puntos')]
final class PointsStatementResource extends JsonResource
{
    /** Wrap the statement of a customer. */
    public function __construct(private readonly PointsStatement $statement)
    {
        parent::__construct($statement);
    }

    /**
     * The balance and the latest 20 movements, newest first.
     *
     * @return array{saldo: int, movimientos: list<array<string, mixed>>}
     */
    public function toArray(Request $request): array
    {
        return [
            'saldo' => $this->statement->balance,
            'movimientos' => array_map(
                fn (PointMovement $movement): array => new PointMovementResource($movement)->toArray($request),
                $this->statement->movements,
            ),
        ];
    }
}
