<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Infrastructure\Persistence\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Schedules\Domain\Contracts\ScheduleRepository;
use Src\Sodas\Schedules\Domain\Entities\Schedule;
use Src\Sodas\Schedules\Domain\Exceptions\InvalidTimeRangeException;
use Src\Sodas\Schedules\Domain\Exceptions\ScheduleOverlapException;
use Src\Sodas\Schedules\Domain\Exceptions\ScheduleSodaNotFoundException;
use Src\Sodas\Schedules\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\Schedules\Domain\ValueObjects\ScheduleId;
use Src\Sodas\Schedules\Domain\ValueObjects\TimeOfDay;
use Src\Sodas\Schedules\Infrastructure\Persistence\Models\ScheduleModel;

final readonly class EloquentScheduleRepository implements ScheduleRepository
{
    private const string EXCLUSION_VIOLATION = '23P01';

    private const string CHECK_VIOLATION = '23514';

    private const string FOREIGN_KEY_VIOLATION = '23503';

    /** Generate a time-ordered UUID for a new slot. */
    public function nextId(): ScheduleId
    {
        return new ScheduleId((string) Str::uuid7());
    }

    /** Insert the slot inside a savepoint, translating constraint violations. */
    public function save(Schedule $schedule): void
    {
        try {
            DB::transaction(fn () => ScheduleModel::query()->create([
                'id' => $schedule->id->value,
                'soda_id' => $schedule->sodaId->value,
                'day_of_week' => $schedule->day->number,
                'opens_at' => $schedule->opensAt->format(),
                'closes_at' => $schedule->closesAt->format(),
            ]));
        } catch (QueryException $exception) {
            throw match ($exception->getCode()) {
                self::FOREIGN_KEY_VIOLATION => ScheduleSodaNotFoundException::create(),
                self::EXCLUSION_VIOLATION => ScheduleOverlapException::create(),
                self::CHECK_VIOLATION => InvalidTimeRangeException::create(),
                default => $exception,
            };
        }
    }

    /**
     * List the slots of the soda ordered by day and opening time.
     *
     * @return list<Schedule>
     */
    public function allOf(SodaId $sodaId): array
    {
        $models = ScheduleModel::query()
            ->where('soda_id', $sodaId->value)
            ->orderBy('day_of_week')
            ->orderBy('opens_at')
            ->get();

        return array_values($models->map($this->toDomain(...))->all());
    }

    /** Rebuild the entity from its stored state; `time` columns come back as `HH:MM:SS`. */
    private function toDomain(ScheduleModel $model): Schedule
    {
        return new Schedule(
            new ScheduleId($model->id),
            new SodaId($model->soda_id),
            new DayOfWeek($model->day_of_week),
            new TimeOfDay(substr($model->opens_at, 0, 5)),
            new TimeOfDay(substr($model->closes_at, 0, 5)),
        );
    }
}
