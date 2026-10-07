<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Str;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Closure;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepositoryContract;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;

final class EloquentClosureRepository implements ClosureRepositoryContract
{
    public function nextId(): ClosureId
    {
        return new ClosureId((string) Str::uuid7());
    }

    public function existsForDate(SodaId $sodaId, ClosureDate $date): bool
    {
        return ClosureModel::where('soda_id', $sodaId->value)
            ->where('date', $date->value())
            ->exists();
    }

    public function save(Closure $closure): void
    {
        ClosureModel::updateOrCreate(
            ['id' => $closure->id()->value],
            [
                'soda_id' => $closure->sodaId()->value,
                'date' => $closure->date()->value(),
                'reason' => $closure->reason()->value(),
            ]
        );
    }

    public function allForSoda(SodaId $sodaId): array
    {
        return ClosureModel::where('soda_id', $sodaId->value)
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn (ClosureModel $model) => new Closure(
                new ClosureId($model->id),
                new SodaId($model->soda_id),
                new ClosureDate((string) $model->date),
                new ClosureReason($model->reason)
            ))
            ->all();
    }

    public function findById(SodaId $sodaId, ClosureId $id): ?Closure
    {
        $model = ClosureModel::where('soda_id', $sodaId->value)
            ->where('id', $id->value)
            ->first();

        if ($model === null) {
            return null;
        }

        return new Closure(
            new ClosureId($model->id),
            new SodaId($model->soda_id),
            new ClosureDate((string) $model->date),
            new ClosureReason($model->reason)
        );
    }

    public function delete(SodaId $sodaId, ClosureId $id): void
    {
        ClosureModel::where('soda_id', $sodaId->value)
            ->where('id', $id->value)
            ->delete();
    }
}
