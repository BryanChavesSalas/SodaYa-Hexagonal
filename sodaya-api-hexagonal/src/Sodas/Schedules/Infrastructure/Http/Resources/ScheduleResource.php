<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Sodas\Schedules\Domain\Entities\Schedule;

/**
 * @property-read Schedule $resource
 */
#[SchemaName('Horario')]
final class ScheduleResource extends JsonResource
{
    /** Wrap a schedule slot. */
    public function __construct(private readonly Schedule $schedule)
    {
        parent::__construct($schedule);
    }

    /**
     * Expose the slot with the vocabulary of the business.
     *
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->schedule->id->value,
            'dia_semana' => $this->schedule->day->number,
            'apertura' => $this->schedule->opensAt->format(),
            'cierre' => $this->schedule->closesAt->format(),
        ];
    }
}
