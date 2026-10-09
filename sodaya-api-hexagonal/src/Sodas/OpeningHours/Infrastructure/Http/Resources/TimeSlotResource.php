<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;

/**
 * @property-read TimeSlot $resource
 */
#[SchemaName('Franja')]
final class TimeSlotResource extends JsonResource
{
    /** Wrap a slot of the schedule. */
    public function __construct(private readonly TimeSlot $slot)
    {
        parent::__construct($slot);
    }

    /**
     * Expose the slot with the vocabulary of the business.
     *
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slot->id->value,
            'dia' => $this->slot->day->value,
            'abre' => $this->slot->opensAt->value,
            'cierra' => $this->slot->closesAt->value,
        ];
    }
}
