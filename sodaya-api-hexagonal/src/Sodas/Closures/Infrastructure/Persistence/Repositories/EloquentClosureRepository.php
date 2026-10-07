<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepository;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\Exceptions\ClosureAlreadyExistsException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;

final readonly class EloquentClosureRepository implements ClosureRepository
{
    /** Generate a time-ordered UUID for a new closure. */
    public function nextId(): ClosureId
    {
        return new ClosureId((string) Str::uuid7());
    }

    /** Insert the closure inside a savepoint. */
    public function save(Closure $closure): void
    {
        try {
            DB::transaction(fn () => ClosureModel::query()->updateOrCreate(
                ['id' => $closure->id->value],
                [
                    'soda_id' => $closure->sodaId->value,
                    'closed_on' => $closure->date->value,
                    'reason' => $closure->reason?->value,
                ],
            ));
        } catch (UniqueConstraintViolationException) {
            throw ClosureAlreadyExistsException::create();
        }
    }

    /** Find the closure scoped to its soda. */
    public function find(ClosureId $id, SodaId $sodaId): ?Closure
    {
        $model = ClosureModel::query()
            ->where('soda_id', $sodaId->value)
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** Find the closure of the soda on a date. */
    public function onDate(SodaId $sodaId, ClosureDate $date): ?Closure
    {
        $model = ClosureModel::query()
            ->where('soda_id', $sodaId->value)
            ->where('closed_on', $date->value)
            ->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /**
     * List the closures of the soda from a date onward, ordered by date.
     *
     * @return list<Closure>
     */
    public function from(SodaId $sodaId, ClosureDate $date): array
    {
        $closures = ClosureModel::query()
            ->where('soda_id', $sodaId->value)
            ->where('closed_on', '>=', $date->value)
            ->orderBy('closed_on')
            ->get();

        return array_values($closures->map($this->toDomain(...))->all());
    }

    /** Delete the closure from its soda. */
    public function delete(Closure $closure): void
    {
        ClosureModel::query()
            ->where('soda_id', $closure->sodaId->value)
            ->whereKey($closure->id->value)
            ->delete();
    }

    /** Rebuild the entity from its stored state. */
    private function toDomain(ClosureModel $model): Closure
    {
        return Closure::reconstitute(
            new ClosureId($model->id),
            new SodaId($model->soda_id),
            ClosureDate::fromMoment($model->closed_on),
            $model->reason === null ? null : new ClosureReason($model->reason),
        );
    }
}
