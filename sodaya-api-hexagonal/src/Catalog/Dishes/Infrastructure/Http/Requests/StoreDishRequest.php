<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Catalog\Dishes\Application\DTOs\CreateDishCommand;
use Src\Catalog\Dishes\Domain\ValueObjects\DishDescription;
use Src\Catalog\Dishes\Domain\ValueObjects\DishName;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Catalog\Dishes\Domain\ValueObjects\PreparationTime;
use Src\Catalog\Dishes\Domain\ValueObjects\Price;
use Src\Shared\Domain\Contracts\SodaContext;
use Stringable;

#[SchemaName('CrearPlato')]
final class StoreDishRequest extends FormRequest
{
    /**
     * Validation rules for a new dish of the current soda.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(SodaContext $sodaContext): array
    {
        $sodaId = $sodaContext->current()->value;

        return [
            'nombre' => [
                'required',
                'string',
                'max:'.DishName::MAX_LENGTH,
                Rule::unique('dishes', 'name')->where('soda_id', $sodaId),
            ],
            'descripcion' => ['nullable', 'string', 'max:'.DishDescription::MAX_LENGTH],
            'precio' => ['required', 'integer', 'between:'.Price::MIN.','.Price::MAX],
            'minutos_preparacion' => [
                'required',
                'integer',
                'between:'.PreparationTime::MIN.','.PreparationTime::MAX,
            ],
            'porciones_disponibles' => ['required', 'integer', 'between:'.Portions::MIN.','.Portions::MAX],
            'categoria_id' => [
                'bail',
                'nullable',
                'uuid',
                Rule::exists('categories', 'id')->where('soda_id', $sodaId),
            ],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(SodaContext $sodaContext): CreateDishCommand
    {
        return new CreateDishCommand(
            $sodaContext->current()->value,
            $this->string('nombre')->toString(),
            $this->input('descripcion'),
            $this->integer('precio'),
            $this->integer('minutos_preparacion'),
            $this->integer('porciones_disponibles'),
            $this->input('categoria_id'),
        );
    }
}
