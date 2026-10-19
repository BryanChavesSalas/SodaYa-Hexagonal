<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Ordering\Orders\Application\DTOs\AdvanceOrderCommand;
use Src\Shared\Domain\Contracts\SodaContext;

#[SchemaName('CambioDeEstado')]
final class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Statuses the kitchen can move an order to, in the vocabulary of the API and of the domain.
     *
     * @var array<string, string>
     */
    private const array STATUSES = [
        'confirmado' => 'confirmed',
        'listo' => 'ready',
        'entregado' => 'delivered',
    ];

    /**
     * Validation rules for the target status.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', 'in:'.implode(',', array_keys(self::STATUSES))],
        ];
    }

    /** Map the validated status to the use case input. */
    public function toCommand(SodaContext $sodaContext): AdvanceOrderCommand
    {
        return new AdvanceOrderCommand(
            (string) $this->route('pedido'),
            $sodaContext->current()->value,
            self::STATUSES[$this->string('estado')->toString()],
        );
    }
}
