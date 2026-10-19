<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Src\Ordering\Orders\Application\DTOs\CancelOrderCommand;
use Src\Ordering\Orders\Application\UseCases\AdvanceOrder;
use Src\Ordering\Orders\Application\UseCases\CancelOrder;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Infrastructure\Http\Requests\UpdateOrderStatusRequest;
use Src\Ordering\Orders\Infrastructure\Http\Resources\OrderResource;
use Src\Shared\Domain\Contracts\SodaContext;

#[Group('Pedidos de la cocina', 'Avance y cancelación de los pedidos por el personal de la soda.')]
final readonly class KitchenOrderController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

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
