<?php

declare(strict_types=1);

namespace Src\Catalog\Categories\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Catalog\Categories\Application\UseCases\CreateCategory;
use Src\Catalog\Categories\Application\UseCases\DeleteCategory;
use Src\Catalog\Categories\Application\UseCases\ListCategories;
use Src\Catalog\Categories\Application\UseCases\RenameCategory;
use Src\Catalog\Categories\Infrastructure\Http\Requests\StoreCategoryRequest;
use Src\Catalog\Categories\Infrastructure\Http\Requests\UpdateCategoryRequest;
use Src\Catalog\Categories\Infrastructure\Http\Resources\CategoryResource;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Infrastructure\Http\Requests\ListRequest;
use Src\Shared\Infrastructure\Http\Resources\PageMeta;
use Symfony\Component\HttpFoundation\Response;

#[Group(
    'Categorías de la soda',
    'Administración de las categorías del menú por el personal de la soda.',
)]
final readonly class CategoryController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** List every category of the current soda. */
    #[Endpoint(title: 'Listar las categorías de la soda')]
    public function index(ListRequest $request, ListCategories $listCategories): AnonymousResourceCollection
    {
        $page = $listCategories->execute($this->sodaContext->current()->value, $request->page());

        return CategoryResource::collection($page->items)->additional(PageMeta::of($page));
    }

    /** Register a category for the current soda. */
    #[Endpoint(title: 'Crear una categoría')]
    public function store(
        StoreCategoryRequest $request,
        CreateCategory $createCategory,
    ): JsonResponse {
        $category = $createCategory->execute(
            $request->toCommand($this->sodaContext),
        );

        return new CategoryResource($category)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url().'/'.$category->id->value);
    }

    /** Rename a category of the current soda. */
    #[Endpoint(title: 'Renombrar una categoría')]
    public function update(
        UpdateCategoryRequest $request,
        RenameCategory $renameCategory,
    ): CategoryResource {
        return new CategoryResource(
            $renameCategory->execute(
                $request->toCommand($this->sodaContext),
            ),
        );
    }

    /** Remove a category of the current soda. */
    #[Endpoint(title: 'Eliminar una categoría')]
    public function destroy(
        string $categoria,
        DeleteCategory $deleteCategory,
    ): Response {
        $deleteCategory->execute(
            $categoria,
            $this->sodaContext->current()->value,
        );

        return response()->noContent();
    }
}
