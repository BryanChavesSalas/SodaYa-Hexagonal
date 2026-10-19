<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;

#[SchemaName('CrearPedido')]
final class PlaceOrderRequest extends FormRequest
{
    /**
     * Validation rules for the shape of a new order; the ranges belong to the domain.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Líneas del pedido, una por plato: de 1 a 20. */
            'lineas' => ['present', 'array'],
            'lineas.*.plato_id' => ['required', 'uuid'],
            /** Porciones del plato, de 1 a 20. */
            'lineas.*.cantidad' => ['required', 'integer'],
        ];
    }

    /** Map the validated payload to the use case input: the customer comes from the token and the soda from the URL. */
    public function toCommand(): PlaceOrderCommand
    {
        /** @var list<array{plato_id: string, cantidad: int}> $lines */
        $lines = $this->validated('lineas');

        return new PlaceOrderCommand(
            (string) $this->user()?->getAuthIdentifier(),
            (string) $this->route('soda'),
            array_map(fn (array $line): array => ['dish_id' => $line['plato_id'], 'quantity' => $line['cantidad']], $lines),
        );
    }
}
