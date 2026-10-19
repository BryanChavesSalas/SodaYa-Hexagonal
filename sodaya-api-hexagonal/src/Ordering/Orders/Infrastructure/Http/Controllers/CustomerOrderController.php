<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Src\Ordering\Orders\Application\UseCases\PlaceOrder;
use Src\Ordering\Orders\Infrastructure\Http\Requests\PlaceOrderRequest;
use Src\Ordering\Orders\Infrastructure\Http\Resources\OrderResource;
use Symfony\Component\HttpFoundation\Response;

#[Group('Pedidos del cliente', 'Pedidos para llevar que hace el cliente autenticado.')]
final readonly class CustomerOrderController
{
    /** Place an order at the soda of the URL on behalf of the authenticated customer. */
    #[Endpoint(
        title: 'Hacer un pedido',
        description: 'Crea un pedido pendiente con de 1 a 20 líneas, una por plato, y descuenta sus porciones. El nombre y el precio de cada línea salen del catálogo de la soda en ese momento: cualquier precio o total enviado se ignora. Responde 422 `pedido-vacio` sin líneas, 422 `cantidad-invalida` con una cantidad fuera de 1 a 20 y 422 `plato-no-pertenece-a-la-soda` con un plato de otra soda; 409 `plato-no-disponible` con un plato desactivado, 409 `soda-cerrada` fuera del horario o en un cierre y 409 `porciones-agotadas` cuando un plato no alcanza.',
    )]
    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): JsonResponse
    {
        $order = $placeOrder->execute($request->toCommand(), now()->toDateTimeImmutable());

        return new OrderResource($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', url('/api/v1/pedidos/'.$order->id->value));
    }
}
