<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Billing\Payments\Application\DTOs\PayOrderCommand;

#[SchemaName('PagarPedido')]
final class PayOrderRequest extends FormRequest
{
    /**
     * Validation rules for the shape of a payment: only the payment method travels; the amount is never accepted.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Método de pago del proveedor en modo de prueba: `pm_card_visa` aprueba y `pm_card_visa_chargeDeclined` rechaza. */
            'metodo_pago' => ['required', 'string', 'regex:/^pm_[A-Za-z0-9_]{1,80}$/'],
        ];
    }

    /** Map the request to the use case input: the order from the URL, the customer from the token and the payment method from the body. */
    public function toCommand(): PayOrderCommand
    {
        return new PayOrderCommand(
            (string) $this->route('pedido'),
            (string) $this->user()?->getAuthIdentifier(),
            $this->string('metodo_pago')->toString(),
        );
    }
}
