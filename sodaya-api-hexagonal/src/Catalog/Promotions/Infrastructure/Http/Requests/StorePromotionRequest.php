<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Catalog\Promotions\Application\DTOs\DefinePromotionCommand;
use Src\Shared\Domain\Contracts\SodaContext;

#[SchemaName('CrearPromocion')]
final class StorePromotionRequest extends FormRequest
{
    /**
     * Validation rules for the shape of a new happy hour; the ranges and the order of the times belong to the domain.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Día de la semana ISO 8601: 1 es lunes y 7 es domingo. */
            'dia' => ['required', 'integer'],
            /** Hora de inicio, HH:MM, incluida en la promoción. */
            'inicio' => ['required', 'date_format:H:i'],
            /** Hora de fin, HH:MM, excluida de la promoción. */
            'fin' => ['required', 'date_format:H:i'],
            /** Descuento en porcentaje, de 1 a 90. */
            'porcentaje' => ['required', 'integer'],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(SodaContext $sodaContext): DefinePromotionCommand
    {
        return new DefinePromotionCommand(
            $sodaContext->current()->value,
            $this->integer('dia'),
            $this->string('inicio')->toString(),
            $this->string('fin')->toString(),
            $this->integer('porcentaje'),
        );
    }
}
