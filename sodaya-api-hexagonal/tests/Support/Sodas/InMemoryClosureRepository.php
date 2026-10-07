<?php

declare(strict_types=1);

namespace Tests\Support\Sodas;

use Illuminate\Support\Str;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Closure;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepositoryContract;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;

final class InMemoryClosureRepository implements ClosureRepositoryContract
{
    /** @var array<string, Closure> */
    private array $closures = [];

    public function nextId(): ClosureId
    {
        return new ClosureId((string) Str::uuid7());
    }

    public function existsForDate(SodaId $sodaId, ClosureDate $date): bool
    {
        foreach ($this->closures as $closure) {
            if ($closure->sodaId()->equals($sodaId) && $closure->date()->value() === $date->value()) {
                return true;
            }
        }

        return false;
    }

    public function save(Closure $closure): void
    {
        $this->closures[$closure->id()->value] = $closure;
    }

    public function allForSoda(SodaId $sodaId): array
    {
        return array_values(array_filter(
            $this->closures,
            fn (Closure $c) => $c->sodaId()->equals($sodaId)
        ));
    }

    public function findById(SodaId $sodaId, ClosureId $id): ?Closure
    {
        $closure = $this->closures[$id->value] ?? null;

        if ($closure !== null && $closure->sodaId()->equals($sodaId)) {
            return $closure;
        }

        return null;
    }

    public function delete(SodaId $sodaId, ClosureId $id): void
    {
        if ($this->findById($sodaId, $id) !== null) {
            unset($this->closures[$id->value]);
        }
    }
}
