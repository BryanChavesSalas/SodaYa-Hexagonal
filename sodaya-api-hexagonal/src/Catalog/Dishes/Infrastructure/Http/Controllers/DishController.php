<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Http\Controllers;

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

final readonly class DishController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** List every dish of the current soda. */
    public function index(ListDishes $listDishes): AnonymousResourceCollection
    {
        return DishResource::collection($listDishes->execute($this->sodaContext->current()->value));
    }

    /** Register a dish for the current soda. */
    public function store(StoreDishRequest $request, CreateDish $createDish): JsonResponse
    {
        $dish = $createDish->execute($request->toCommand($this->sodaContext));

        return new DishResource($dish)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', $request->url().'/'.$dish->id->value);
    }

    /** Change or deactivate a dish of the current soda. */
    public function update(UpdateDishRequest $request, UpdateDish $updateDish): DishResource
    {
        return new DishResource($updateDish->execute($request->toCommand($this->sodaContext)));
    }
}
