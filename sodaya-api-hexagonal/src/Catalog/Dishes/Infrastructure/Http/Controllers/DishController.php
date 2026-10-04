<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Catalog\Dishes\Application\UseCases\CreateDish;
use Src\Catalog\Dishes\Application\UseCases\ListDishes;
use Src\Catalog\Dishes\Application\UseCases\UpdateDish;
use Src\Catalog\Dishes\Infrastructure\Http\Requests\StoreDishRequest;
use Src\Catalog\Dishes\Infrastructure\Http\Requests\UpdateDishRequest;
use Src\Catalog\Dishes\Infrastructure\Http\Resources\DishResource;
use Src\Shared\Domain\Contracts\SodaContext;
use Symfony\Component\HttpFoundation\Response;

#[Group('Platos de la soda', 'Administración de los platos por el personal de la soda.')]
final readonly class DishController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** List every dish of the current soda. */
    #[Endpoint(title: 'Listar los platos de la soda')]
    public function index(ListDishes $listDishes): AnonymousResourceCollection
    {
        return DishResource::collection($listDishes->execute($this->sodaContext->current()->value));
    }

    /** Register a dish for the current soda. */
    #[Endpoint(title: 'Crear un plato')]
    public function store(StoreDishRequest $request, CreateDish $createDish): JsonResponse
    {
        $dish = $createDish->execute($request->toCommand($this->sodaContext));

        return new DishResource($dish)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url().'/'.$dish->id->value);
    }

    /** Change or deactivate a dish of the current soda. */
    #[Endpoint(title: 'Editar o desactivar un plato')]
    public function update(UpdateDishRequest $request, UpdateDish $updateDish): DishResource
    {
        return new DishResource($updateDish->execute($request->toCommand($this->sodaContext)));
    }
}
