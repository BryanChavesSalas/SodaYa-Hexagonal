<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Catalog\Categories\Application\DTOs\RenameCategoryCommand;
use Src\Catalog\Categories\Domain\ValueObjects\CategoryName;
use Src\Shared\Domain\Contracts\SodaContext;
use Stringable;

#[SchemaName('RenombrarCategoria')]
final class UpdateCategoryRequest extends FormRequest
{
    /**
     * Validation rules to rename a category.
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
                'max:'.CategoryName::MAX_LENGTH,
                Rule::unique('categories', 'name')
                    ->where('soda_id', $sodaId)
                    ->ignore($this->route('categoria')),
            ],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(SodaContext $sodaContext): RenameCategoryCommand
    {
        $validated = $this->validated();

        return new RenameCategoryCommand(
            (string) $this->route('categoria'),
            $sodaContext->current()->value,
            $validated['nombre'],
        );
    }
}
