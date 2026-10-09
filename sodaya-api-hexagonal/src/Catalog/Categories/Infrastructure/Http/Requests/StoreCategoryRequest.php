<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Catalog\Categories\Application\DTOs\CreateCategoryCommand;
use Src\Catalog\Categories\Domain\ValueObjects\CategoryName;
use Src\Shared\Domain\Contracts\SodaContext;
use Stringable;

#[SchemaName('CrearCategoria')]
final class StoreCategoryRequest extends FormRequest
{
    /**
     * Validation rules for a new category of the current soda.
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
                Rule::unique('categories', 'name')->where('soda_id', $sodaId),
            ],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(SodaContext $sodaContext): CreateCategoryCommand
    {
        return new CreateCategoryCommand(
            $sodaContext->current()->value,
            $this->string('nombre')->toString(),
        );
    }
}
