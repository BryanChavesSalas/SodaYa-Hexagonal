<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\Schedules\Application\UseCases\CreateScheduleUseCase;
use Src\Sodas\Schedules\Application\UseCases\ListSchedulesUseCase;
use Src\Sodas\Schedules\Infrastructure\Http\Requests\StoreScheduleRequest;
use Src\Sodas\Schedules\Infrastructure\Http\Resources\ScheduleResource;
use Symfony\Component\HttpFoundation\Response;

#[Group('Horario de atención', 'Franjas en las que la soda atiende cada día, en la hora de America/Costa_Rica.')]
final readonly class ScheduleController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** List the slots of the current soda ordered by day and opening time. */
    #[Endpoint(title: 'Listar el horario de atención')]
    public function index(ListSchedulesUseCase $listSchedules): AnonymousResourceCollection
    {
        return ScheduleResource::collection($listSchedules->execute($this->sodaContext->current()->value));
    }

    /** Register a slot of the current soda; closing is excluded. */
    #[Endpoint(title: 'Agregar una franja de atención')]
    public function store(StoreScheduleRequest $request, CreateScheduleUseCase $createSchedule): JsonResponse
    {
        $schedule = $createSchedule->execute($request->toCommand($this->sodaContext));

        return new ScheduleResource($schedule)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
