<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Catalog\Dishes\Application\DTOs\UpdateDishCommand;
use Src\Catalog\Dishes\Domain\ValueObjects\DishDescription;
use Src\Catalog\Dishes\Domain\ValueObjects\DishName;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Catalog\Dishes\Domain\ValueObjects\PreparationTime;
use Src\Catalog\Dishes\Domain\ValueObjects\Price;
use Src\Shared\Domain\Contracts\SodaContext;
use Stringable;

final class UpdateDishRequest extends FormRequest
{
    /**
     * Payload fields paired with the names the use case expects.
     *
     * @var array<string, string>
     */
    private const array FIELDS = [
        'nombre' => 'name',
        'descripcion' => 'description',
        'precio' => 'price',
        'minutos_preparacion' => 'preparation_minutes',
        'porciones_disponibles' => 'available_portions',
        'categoria_id' => 'category_id',
        'activo' => 'active',
    ];

    /**
     * Validation rules for a partial update of a dish.
     *
     * @return array<string, list<Stringable|string>>
     */
    public function rules(SodaContext $sodaContext): array
    {
        $sodaId = $sodaContext->current()->value;

        return [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:'.DishName::MAX_LENGTH,
                Rule::unique('dishes', 'name')->where('soda_id', $sodaId)->ignore($this->route('plato')),
            ],
            'descripcion' => ['sometimes', 'nullable', 'string', 'max:'.DishDescription::MAX_LENGTH],
            'precio' => ['sometimes', 'required', 'integer', 'between:'.Price::MIN.','.Price::MAX],
            'minutos_preparacion' => [
                'sometimes',
                'required',
                'integer',
                'between:'.PreparationTime::MIN.','.PreparationTime::MAX,
            ],
            'porciones_disponibles' => [
                'sometimes',
                'required',
                'integer',
                'between:'.Portions::MIN.','.Portions::MAX,
            ],
            'categoria_id' => [
                'bail',
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('categories', 'id')->where('soda_id', $sodaId),
            ],
            'activo' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(SodaContext $sodaContext): UpdateDishCommand
    {
        $changes = [];

        foreach ($this->validated() as $field => $value) {
            $changes[self::FIELDS[$field]] = $value;
        }

        return new UpdateDishCommand(
            (string) $this->route('plato'),
            $sodaContext->current()->value,
            $changes,
        );
    }
}
