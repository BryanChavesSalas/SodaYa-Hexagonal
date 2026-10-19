<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Loyalty\Points\Application\UseCases\GetPointsStatement;
use Src\Loyalty\Points\Application\UseCases\RedeemPoints;
use Src\Loyalty\Points\Infrastructure\Http\Requests\RedeemPointsRequest;
use Src\Loyalty\Points\Infrastructure\Http\Resources\PointMovementResource;
use Src\Loyalty\Points\Infrastructure\Http\Resources\PointsStatementResource;

#[Group('Puntos', 'Saldo y canje de los puntos de lealtad del cliente autenticado.')]
final readonly class PointsController
{
    /** Balance and latest movements of the authenticated customer. */
    #[Endpoint(
        title: 'Consultar el saldo de puntos',
        description: 'Responde el saldo del cliente autenticado y sus últimos 20 movimientos, del más reciente al más antiguo. Cada cliente solo ve sus propios puntos.',
    )]
    public function show(Request $request, GetPointsStatement $getStatement): PointsStatementResource
    {
        return new PointsStatementResource($getStatement->execute((string) $request->user()?->getAuthIdentifier()));
    }

    /** Spend points of the authenticated customer. */
    #[Endpoint(
        title: 'Canjear puntos',
        description: 'Registra un canje de puntos del cliente autenticado y responde el saldo que queda. Un canje mayor que el saldo responde 409 `puntos-insuficientes`. El valor del punto y su aplicación como descuento están pendientes de definir.',
    )]
    public function redeem(RedeemPointsRequest $request, RedeemPoints $redeemPoints): JsonResponse
    {
        $redemption = $redeemPoints->execute($request->toCommand(), now()->toDateTimeImmutable());

        return new PointMovementResource($redemption->movement)
            ->additional(['saldo' => $redemption->balance])
            ->response()
            ->setStatusCode(201);
    }
}
