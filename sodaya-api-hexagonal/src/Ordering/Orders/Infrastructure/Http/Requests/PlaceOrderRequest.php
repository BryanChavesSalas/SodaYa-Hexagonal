<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Domain\ValueObjects\IdempotencyKey;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[SchemaName('CrearPedido')]
final class PlaceOrderRequest extends FormRequest
{
    public const string IDEMPOTENCY_HEADER = 'Idempotency-Key';

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

    /**
     * Map the validated payload to the use case input: the customer comes from the token, the soda from the URL
     * and the key of the request from its Idempotency-Key header.
     */
    public function toCommand(): PlaceOrderCommand
    {
        /** @var list<array{plato_id: string, cantidad: int}> $lines */
        $lines = $this->validated('lineas');

        return new PlaceOrderCommand(
            (string) $this->user()?->getAuthIdentifier(),
            (string) $this->route('soda'),
            array_map(fn (array $line): array => ['dish_id' => $line['plato_id'], 'quantity' => $line['cantidad']], $lines),
            $this->idempotencyKey(),
        );
    }

    /** Key of the request, from 1 to 100 characters; without it the request cannot be told apart from a retry. */
    private function idempotencyKey(): string
    {
        $key = trim((string) $this->header(self::IDEMPOTENCY_HEADER, ''));

        if ($key === '' || mb_strlen($key) > IdempotencyKey::MAX_LENGTH) {
            throw new BadRequestHttpException(__('ordering.idempotency_key_invalid', ['max' => IdempotencyKey::MAX_LENGTH]));
        }

        return $key;
    }
}
