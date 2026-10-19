<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Http\Requests;

use Src\Shared\Infrastructure\Http\Requests\ListRequest;

final class ListKitchenOrdersRequest extends ListRequest
{
    /**
     * Statuses the kitchen can filter by, in the vocabulary of the API and of the domain.
     *
     * @var array<string, string>
     */
    private const array STATUSES = [
        'pendiente' => 'pending',
        'confirmado' => 'confirmed',
        'listo' => 'ready',
        'entregado' => 'delivered',
        'cancelado' => 'cancelled',
    ];

    /**
     * Validation rules for the page and the optional status filter.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Estado por el que filtrar: pendiente, confirmado, listo, entregado o cancelado. Sin él se listan todos. */
            'estado' => ['sometimes', 'string', 'in:'.implode(',', array_keys(self::STATUSES))],
        ];
    }

    /** Status asked for, in the vocabulary of the domain, or null for every status. */
    public function status(): ?string
    {
        return $this->has('estado') ? self::STATUSES[$this->string('estado')->toString()] : null;
    }
}
