<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Sodas\Closures\Application\DTOs\RegisterClosureCommand;
use Src\Sodas\Closures\Application\UseCases\CloseForToday;
use Src\Sodas\Closures\Application\UseCases\DeleteClosure;
use Src\Sodas\Closures\Application\UseCases\ListClosures;
use Src\Sodas\Closures\Application\UseCases\RegisterClosure;
use Src\Sodas\Closures\Infrastructure\Http\Requests\CloseForTodayRequest;
use Src\Sodas\Closures\Infrastructure\Http\Requests\StoreClosureRequest;
use Src\Sodas\Closures\Infrastructure\Http\Resources\ClosureResource;

final class ClosureController
{
    /**
     * @return AnonymousResourceCollection<ClosureResource>
     */
    public function index(ListClosures $useCase): AnonymousResourceCollection
    {
        return ClosureResource::collection($useCase->execute());
    }

    public function store(StoreClosureRequest $request, RegisterClosure $useCase): JsonResponse
    {
        $closure = $useCase->execute(new RegisterClosureCommand(
            date: (string) $request->validated('fecha'),
            reason: $request->validated('motivo')
        ));

        return (new ClosureResource($closure))
            ->response()
            ->setStatusCode(201);
    }

    public function closeToday(CloseForTodayRequest $request, CloseForToday $useCase): JsonResponse
    {
        $closure = $useCase->execute($request->validated('motivo'));

        return (new ClosureResource($closure))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(string $cierre, DeleteClosure $useCase): JsonResponse
    {
        $useCase->execute($cierre);

        return response()->json(null, 204);
    }
}
