<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Ordering\Orders\Application\UseCases\GetCustomerOrder;
use Src\Ordering\Orders\Application\UseCases\ListCustomerOrders;
use Src\Ordering\Orders\Application\UseCases\PlaceOrder;
use Src\Ordering\Orders\Infrastructure\Http\Requests\PlaceOrderRequest;
use Src\Ordering\Orders\Infrastructure\Http\Resources\CustomerOrderResource;
use Src\Ordering\Orders\Infrastructure\Http\Resources\OrderResource;
use Src\Shared\Infrastructure\Http\Requests\ListRequest;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;
use Symfony\Component\HttpFoundation\Response;

#[Group('Pedidos del cliente', 'Pedidos para llevar que hace el cliente autenticado.')]
final readonly class CustomerOrderController
{
    /** List the orders of the authenticated customer, newest first. */
    #[Endpoint(
        title: 'Consultar mis pedidos',
        description: 'Lista los pedidos del cliente autenticado en cualquier soda, del más reciente al más antiguo, paginados con `pagina` y `por_pagina`. Cada pedido trae en `acciones` los enlaces de lo que el cliente puede hacer con él en su estado: `pagar` mientras está pendiente y sin pago aprobado y `cancelar` mientras está pendiente o confirmado; en un estado final no hay acciones.',
    )]
    public function index(ListRequest $request, ListCustomerOrders $listOrders): AnonymousResourceCollection
    {
        $page = $listOrders->execute((string) $request->user()?->getAuthIdentifier(), $request->page());

        return CustomerOrderResource::collection($page->items)->additional(PageMeta::of($page));
    }

    /** Show one order of the authenticated customer. */
    #[Endpoint(
        title: 'Consultar un pedido mío',
        description: 'Responde un pedido del cliente autenticado con sus líneas, su número de retiro, su hora estimada de retiro y las acciones disponibles en su estado. Un pedido ajeno responde el mismo 404 `pedido-no-encontrado` que uno que no existe.',
    )]
    public function show(Request $request, string $pedido, GetCustomerOrder $getOrder): CustomerOrderResource
    {
        return new CustomerOrderResource($getOrder->execute($pedido, (string) $request->user()?->getAuthIdentifier()));
    }

    /** Place an order at the soda of the URL on behalf of the authenticated customer, once per Idempotency-Key. */
    #[Endpoint(
        title: 'Hacer un pedido',
        description: 'Crea un pedido pendiente con de 1 a 20 líneas, una por plato, y descuenta sus porciones. El nombre y el precio de cada línea salen del catálogo de la soda en ese momento: cualquier precio o total enviado se ignora. Exige el encabezado `Idempotency-Key`, de 1 a 100 caracteres, elegido por el cliente para cada pedido; sin él responde 400 `solicitud-invalida`. Repetir la solicitud con la misma clave y las mismas líneas responde 200 con el pedido ya creado, sin descontar porciones de nuevo; la misma clave con otras líneas responde 409 `conflicto`. Responde 422 `pedido-vacio` sin líneas, 422 `cantidad-invalida` con una cantidad fuera de 1 a 20 y 422 `plato-no-pertenece-a-la-soda` con un plato de otra soda; 409 `plato-no-disponible` con un plato desactivado, 409 `soda-cerrada` fuera del horario o en un cierre y 409 `porciones-agotadas` cuando un plato no alcanza.',
    )]
    public function store(PlaceOrderRequest $request, PlaceOrder $placeOrder): JsonResponse
    {
        $placed = $placeOrder->execute($request->toCommand(), now()->toDateTimeImmutable());

        return new OrderResource($placed->order)
            ->response()
            ->setStatusCode($placed->created ? Response::HTTP_CREATED : Response::HTTP_OK)
            ->header('Location', url('/api/v1/pedidos/'.$placed->order->id->value));
    }
}
