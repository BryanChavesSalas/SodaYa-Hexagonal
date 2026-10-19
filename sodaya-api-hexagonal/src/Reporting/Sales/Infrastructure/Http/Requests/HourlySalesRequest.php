<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class HourlySalesRequest extends FormRequest
{
    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * Validation rules for the date and the minimum of orders.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Fecha del reporte, AAAA-MM-DD. Por defecto, hoy en Costa Rica. */
            'fecha' => ['bail', 'sometimes', 'date_format:'.self::DATE_FORMAT],
            /** Cantidad mínima de pedidos que debe tener una hora para aparecer. Por defecto, 1. */
            'minimo_pedidos' => ['sometimes', 'integer', 'between:1,1000'],
        ];
    }

    /** Date asked for, today when it is missing. */
    public function reportDate(): string
    {
        return $this->string('fecha', now()->format(self::DATE_FORMAT))->toString();
    }

    /** Minimum of orders an hour needs, 1 when it is missing. */
    public function minimumOrders(): int
    {
        return $this->integer('minimo_pedidos', 1);
    }
}
