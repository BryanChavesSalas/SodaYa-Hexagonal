<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\OpeningHours\Domain\Contracts\TimeSlotRepository;
use Src\Sodas\OpeningHours\Domain\Entities\TimeSlot;
use Src\Sodas\OpeningHours\Domain\Exceptions\TimeSlotOverlapsException;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeOfDay;
use Src\Sodas\OpeningHours\Domain\ValueObjects\TimeSlotId;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;

final readonly class EloquentTimeSlotRepository implements TimeSlotRepository
{
    private const string EXCLUSION_VIOLATION = '23P01';

    /** Generate a time-ordered UUID for a new slot. */
    public function nextId(): TimeSlotId
    {
        return new TimeSlotId((string) Str::uuid7());
    }

    /** Insert the slot inside a savepoint and translate an exclusion violation. */
    public function save(TimeSlot $slot): void
    {
        try {
            DB::transaction(fn () => TimeSlotModel::query()->updateOrCreate(
                ['id' => $slot->id->value],
                [
                    'soda_id' => $slot->sodaId->value,
                    'day_of_week' => $slot->day->value,
                    'opens_at' => $slot->opensAt->value,
                    'closes_at' => $slot->closesAt->value,
                ],
            ));
        } catch (QueryException $exception) {
            throw $exception->getCode() === self::EXCLUSION_VIOLATION
                ? TimeSlotOverlapsException::create()
                : $exception;
        }
    }

    /** Find the slot scoped to its soda. */
    public function find(TimeSlotId $id, SodaId $sodaId): ?TimeSlot
    {
        $model = TimeSlotModel::query()
            ->where('soda_id', $sodaId->value)
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * List the slots of the soda ordered by day and opening time.
     *
     * @return list<TimeSlot>
     */
    public function allOf(SodaId $sodaId): array
    {
        return $this->toDomainList($this->orderedSlotsOf($sodaId));
    }

    /**
     * List the slots of the soda on one day ordered by opening time.
     *
     * @return list<TimeSlot>
     */
    public function ofDay(SodaId $sodaId, DayOfWeek $day): array
    {
        return $this->toDomainList($this->orderedSlotsOf($sodaId)->where('day_of_week', $day->value));
    }

    /** Delete the row of the slot. */
    public function delete(TimeSlot $slot): void
    {
        TimeSlotModel::query()
            ->where('soda_id', $slot->sodaId->value)
            ->whereKey($slot->id->value)
            ->delete();
    }

    /**
     * Scope the query to a soda in schedule order.
     *
     * @return Builder<TimeSlotModel>
     */
    private function orderedSlotsOf(SodaId $sodaId): Builder
    {
        return TimeSlotModel::query()
            ->where('soda_id', $sodaId->value)
            ->orderBy('day_of_week')
            ->orderBy('opens_at');
    }

    /**
     * Run the query and rebuild every slot.
     *
     * @param  Builder<TimeSlotModel>  $query
     * @return list<TimeSlot>
     */
    private function toDomainList(Builder $query): array
    {
        return array_values($query->get()->map($this->toDomain(...))->all());
    }

    /** Rebuild the slot from its stored state, dropping the seconds PostgreSQL returns. */
    private function toDomain(TimeSlotModel $model): TimeSlot
    {
        return TimeSlot::reconstitute(
            new TimeSlotId($model->id),
            new SodaId($model->soda_id),
            new DayOfWeek($model->day_of_week),
            new TimeOfDay(substr($model->opens_at, 0, 5)),
            new TimeOfDay(substr($model->closes_at, 0, 5)),
        );
    }
}
