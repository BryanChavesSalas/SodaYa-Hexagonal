<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\OpeningHours\Application\UseCases\AddTimeSlot;
use Src\Sodas\OpeningHours\Application\UseCases\DeleteTimeSlot;
use Src\Sodas\OpeningHours\Application\UseCases\ListTimeSlots;
use Src\Sodas\OpeningHours\Infrastructure\Http\Requests\StoreTimeSlotRequest;
use Src\Sodas\OpeningHours\Infrastructure\Http\Resources\TimeSlotResource;

#[Group('Horario de la soda', 'Franjas de atención por día de la semana; el día 1 es lunes y el 7 es domingo.')]
final readonly class TimeSlotController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** List the schedule of the current soda. */
    #[Endpoint(title: 'Consultar el horario de la soda')]
    public function index(ListTimeSlots $listTimeSlots): AnonymousResourceCollection
    {
        return TimeSlotResource::collection($listTimeSlots->execute($this->sodaContext->current()->value));
    }

    /** Add a slot to the schedule of the current soda. */
    #[Endpoint(
        title: 'Agregar una franja al horario',
        description: 'La apertura está incluida en la franja y el cierre excluido. Responde 409 si se traslapa con otra franja del mismo día.',
    )]
    public function store(StoreTimeSlotRequest $request, AddTimeSlot $addTimeSlot): JsonResponse
    {
        return new TimeSlotResource($addTimeSlot->execute($request->toCommand($this->sodaContext)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /** Remove a slot from the schedule of the current soda. */
    #[Endpoint(title: 'Eliminar una franja del horario')]
    public function destroy(string $franja, DeleteTimeSlot $deleteTimeSlot): Response
    {
        $deleteTimeSlot->execute($franja, $this->sodaContext->current()->value);

        return response()->noContent();
    }
}
