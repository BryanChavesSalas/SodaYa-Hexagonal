<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Ordering\Orders\Application\DTOs\CancelOrderCommand;
use Src\Ordering\Orders\Application\UseCases\AdvanceOrder;
use Src\Ordering\Orders\Application\UseCases\CancelOrder;
use Src\Ordering\Orders\Application\UseCases\ListKitchenOrders;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Infrastructure\Http\Requests\ListKitchenOrdersRequest;
use Src\Ordering\Orders\Infrastructure\Http\Requests\UpdateOrderStatusRequest;
use Src\Ordering\Orders\Infrastructure\Http\Resources\OrderResource;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;

#[Group('Pedidos de la cocina', 'Lista, avance y cancelación de los pedidos por el personal de la soda.')]
final readonly class KitchenOrderController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** List the orders of the current soda, in one status or in any, oldest first. */
    #[Endpoint(
        title: 'Listar los pedidos de la soda',
        description: 'Lista los pedidos de la soda del personal autenticado, del más antiguo al más reciente: el orden en que llegaron es el orden en que se preparan. `estado` filtra por pendiente, confirmado, listo, entregado o cancelado; sin él se listan todos. Cada pedido trae su número de retiro, su hora estimada de retiro y sus líneas. Paginado con `pagina` y `por_pagina`: 15 por defecto y 50 como máximo.',
    )]
    public function index(ListKitchenOrdersRequest $request, ListKitchenOrders $listOrders): AnonymousResourceCollection
    {
        $page = $listOrders->execute($this->sodaContext->current()->value, $request->status(), $request->page());

        return OrderResource::collection($page->items)->additional(PageMeta::of($page));
    }

    /** Move an order of the current soda one step forward. */
    #[Endpoint(
        title: 'Avanzar el estado de un pedido',
        description: 'El pedido avanza un paso: pendiente, confirmado, listo, entregado. Un salto o un retroceso responde 409 `transicion-no-permitida`, y confirmar un pedido sin pago aprobado responde 409 `pedido-sin-pago`.',
    )]
    public function updateStatus(UpdateOrderStatusRequest $request, AdvanceOrder $advanceOrder): OrderResource
    {
        return new OrderResource($advanceOrder->execute($request->toCommand($this->sodaContext), now()->toDateTimeImmutable()));
    }

    /** Cancel an order of the current soda that the kitchen cannot prepare. */
    #[Endpoint(
        title: 'Cancelar un pedido desde la cocina',
        description: 'Cancela un pedido pendiente o confirmado y registra que la cancelación la originó la cocina. Las porciones vuelven al menú y, si el pedido estaba pagado, se solicita el reembolso. Un pedido listo o entregado responde 409 `transicion-no-permitida`. Cancelar un pedido ya cancelado no lo cambia.',
    )]
    public function cancel(string $pedido, CancelOrder $cancelOrder): OrderResource
    {
        $command = new CancelOrderCommand($pedido, $this->sodaContext->current()->value, CancellationOrigin::Kitchen->value);

        return new OrderResource($cancelOrder->execute($command, now()->toDateTimeImmutable()));
    }
}
