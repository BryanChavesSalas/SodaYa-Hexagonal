<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Resources;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;

/**
 * @property-read Payment $resource
 */
#[SchemaName('Pago')]
final class PaymentResource extends JsonResource
{
    /** Wrap a payment aggregate. */
    public function __construct(private readonly Payment $payment)
    {
        parent::__construct($payment);
    }

    /**
     * Expose the payment with the vocabulary of the business; the amount is in colones.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->payment->id->value,
            'pedido_id' => $this->payment->orderId->value,
            'proveedor' => $this->payment->provider,
            'referencia' => $this->payment->reference?->value,
            'monto' => $this->payment->amount->colones,
            'estado' => self::status($this->payment->status),
            'codigo_rechazo' => $this->payment->declineCode,
            'creado_en' => self::moment($this->payment->openedAt),
            'cerrado_en' => self::moment($this->payment->settledAt),
        ];
    }

    /** Status of the payment in the vocabulary of the API. */
    private static function status(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Pending => 'pendiente',
            PaymentStatus::Approved => 'aprobado',
            PaymentStatus::Rejected => 'rechazado',
        };
    }

    /** Moment in ISO 8601 with the offset of Costa Rica. */
    private static function moment(?DateTimeImmutable $moment): ?string
    {
        return $moment?->setTimezone(new DateTimeZone(config()->string('app.timezone')))->format(DateTimeInterface::ATOM);
    }
}
