<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\Closures\Application\UseCases\CloseForToday;
use Src\Sodas\Closures\Application\UseCases\DeleteClosure;
use Src\Sodas\Closures\Application\UseCases\ListClosures;
use Src\Sodas\Closures\Application\UseCases\RegisterClosure;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Infrastructure\Http\Requests\CloseForTodayRequest;
use Src\Sodas\Closures\Infrastructure\Http\Requests\StoreClosureRequest;
use Src\Sodas\Closures\Infrastructure\Http\Resources\ClosureResource;

#[Group('Cierres excepcionales', 'Días en que la soda no abre aunque su horario diga lo contrario.')]
final readonly class ClosureController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** List the closures of the current soda from today onward. */
    #[Endpoint(title: 'Listar los cierres de la soda')]
    public function index(ListClosures $listClosures): AnonymousResourceCollection
    {
        return ClosureResource::collection(
            $listClosures->execute($this->sodaContext->current()->value, now()->toDateTimeImmutable()),
        );
    }

    /** Register a closure of the current soda for a date. */
    #[Endpoint(title: 'Registrar un cierre')]
    public function store(StoreClosureRequest $request, RegisterClosure $registerClosure): JsonResponse
    {
        $closure = $registerClosure->execute($request->toCommand($this->sodaContext), now()->toDateTimeImmutable());

        return $this->created($closure);
    }

    /** Close the current soda for the rest of today. */
    #[Endpoint(title: 'Cerrar por el resto del día')]
    public function closeForToday(CloseForTodayRequest $request, CloseForToday $closeForToday): JsonResponse
    {
        $closure = $closeForToday->execute(
            $this->sodaContext->current()->value,
            $request->reason(),
            now()->toDateTimeImmutable(),
        );

        return $this->created($closure);
    }

    /** Delete a closure so the soda opens again that day. */
    #[Endpoint(title: 'Eliminar un cierre')]
    public function destroy(string $cierre, DeleteClosure $deleteClosure): Response
    {
        $deleteClosure->execute($cierre, $this->sodaContext->current()->value);

        return response()->noContent();
    }

    /** Answer with the new closure and its location. */
    private function created(Closure $closure): JsonResponse
    {
        return new ClosureResource($closure)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED)
            ->header('Location', route('sodas.closures.destroy', ['cierre' => $closure->id->value]));
    }
}
