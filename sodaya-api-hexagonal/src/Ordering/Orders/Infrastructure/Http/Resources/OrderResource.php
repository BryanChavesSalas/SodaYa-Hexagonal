<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Resources;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;

/**
 * @property-read Order $resource
 */
#[SchemaName('Pedido')]
final class OrderResource extends JsonResource
{
    /** Wrap an order aggregate. */
    public function __construct(private readonly Order $order)
    {
        parent::__construct($order);
    }

    /**
     * Expose the order with the vocabulary of the business.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->order->id->value,
            'estado' => self::status($this->order->status),
            'cancelado_por' => self::origin($this->order->cancellationOrigin),
            'pago' => [
                'estado' => $this->order->isPaid() ? 'aprobado' : 'pendiente',
                'aprobado_en' => self::moment($this->order->paidAt),
            ],
            'lineas' => array_map(fn (OrderLine $line): array => [
                'plato_id' => $line->dishId->value,
                'nombre' => $line->dishName,
                'precio_unitario' => $line->unitPrice,
                'cantidad' => $line->quantity->value,
                'subtotal' => $line->subtotal(),
            ], $this->order->lines),
            'total' => $this->order->total(),
            'creado_en' => self::moment($this->order->placedAt),
            'confirmado_en' => self::moment($this->order->changedAt(OrderStatus::Confirmed)),
            'listo_en' => self::moment($this->order->changedAt(OrderStatus::Ready)),
            'entregado_en' => self::moment($this->order->changedAt(OrderStatus::Delivered)),
            'cancelado_en' => self::moment($this->order->changedAt(OrderStatus::Cancelled)),
        ];
    }

    /** Status of the order in the vocabulary of the API. */
    private static function status(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending => 'pendiente',
            OrderStatus::Confirmed => 'confirmado',
            OrderStatus::Ready => 'listo',
            OrderStatus::Delivered => 'entregado',
            OrderStatus::Cancelled => 'cancelado',
        };
    }

    /** Origin of a cancellation in the vocabulary of the API. */
    private static function origin(?CancellationOrigin $origin): ?string
    {
        return match ($origin) {
            null => null,
            CancellationOrigin::Customer => 'cliente',
            CancellationOrigin::Kitchen => 'cocina',
            CancellationOrigin::PaymentTimeout => 'falta_de_pago',
            CancellationOrigin::ConfirmationTimeout => 'falta_de_confirmacion',
        };
    }

    /** Moment in ISO 8601 with the offset of Costa Rica. */
    private static function moment(?DateTimeImmutable $moment): ?string
    {
        return $moment?->setTimezone(new DateTimeZone(config()->string('app.timezone')))->format(DateTimeInterface::ATOM);
    }
}
