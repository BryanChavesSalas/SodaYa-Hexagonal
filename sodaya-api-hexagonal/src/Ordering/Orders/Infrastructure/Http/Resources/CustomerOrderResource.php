<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;

/**
 * @property-read Order $resource
 */
#[SchemaName('PedidoDelCliente')]
final class CustomerOrderResource extends JsonResource
{
    /** Wrap an order aggregate seen by its customer. */
    public function __construct(private readonly Order $order)
    {
        parent::__construct($order);
    }

    /**
     * Expose the order as the kitchen sees it, plus the links of what the customer can do with it in its status.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...new OrderResource($this->order)->toArray($request),
            /**
             * Enlaces a las acciones disponibles según el estado: `pagar` mientras está pendiente y sin pago aprobado, `cancelar` mientras está pendiente o confirmado. Vacío en un estado final.
             *
             * @var array{pagar?: string, cancelar?: string}
             */
            'acciones' => (object) $this->actions(),
        ];
    }

    /**
     * Links of the actions the customer may take on the order right now, by name.
     *
     * @return array<string, string>
     */
    private function actions(): array
    {
        $base = url('/api/v1/pedidos/'.$this->order->id->value);
        $actions = [];

        if ($this->order->status === OrderStatus::Pending && ! $this->order->isPaid()) {
            $actions['pagar'] = $base.'/pago';
        }

        if ($this->order->status->isCancellable()) {
            $actions['cancelar'] = $base.'/cancelacion';
        }

        return $actions;
    }
}
