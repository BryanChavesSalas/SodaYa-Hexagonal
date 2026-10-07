<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Closure;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;

interface ClosureRepositoryContract
{
    public function nextId(): ClosureId;

    public function existsForDate(SodaId $sodaId, ClosureDate $date): bool;

    public function save(Closure $closure): void;

    /** @return array<int, Closure> */
    public function allForSoda(SodaId $sodaId): array;

    public function findById(SodaId $sodaId, ClosureId $id): ?Closure;

    public function delete(SodaId $sodaId, ClosureId $id): void;
}
