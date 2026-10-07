<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Categories\Domain\Entities\Category;

/**
 * @property-read Category $resource
 */
#[SchemaName('Categoria')]
final class CategoryResource extends JsonResource
{
    /** Wrap a category aggregate. */
    public function __construct(private readonly Category $category)
    {
        parent::__construct($category);
    }

    /**
     * Expose the category with the vocabulary of the business.
     *
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->category->id->value,
            'nombre' => $this->category->name->value,
        ];
    }
}
