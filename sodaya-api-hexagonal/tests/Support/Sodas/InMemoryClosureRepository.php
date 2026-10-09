<?php

declare(strict_types=1);

namespace Tests\Support\Sodas;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepository;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\Exceptions\ClosureAlreadyExistsException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;

final class InMemoryClosureRepository implements ClosureRepository
{
    /** @var array<string, Closure> */
    private array $closures = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): ClosureId
    {
        return new ClosureId(sprintf('0192f0c4-0000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store the closure enforcing one closure per soda and date. */
    public function save(Closure $closure): void
    {
        $existing = $this->onDate($closure->sodaId, $closure->date);

        if ($existing !== null && ! $existing->id->equals($closure->id)) {
            throw ClosureAlreadyExistsException::create();
        }

        $this->closures[$closure->id->value] = $closure;
    }

    /** Find the closure scoped to its soda. */
    public function find(ClosureId $id, SodaId $sodaId): ?Closure
    {
        $closure = $this->closures[$id->value] ?? null;

        return $closure?->sodaId->equals($sodaId) === true ? $closure : null;
    }

    /** Find the closure of the soda on a date. */
    public function onDate(SodaId $sodaId, ClosureDate $date): ?Closure
    {
        return array_find(
            $this->closures,
            fn (Closure $closure): bool => $closure->sodaId->equals($sodaId) && $closure->date->value === $date->value,
        );
    }

    /**
     * List the closures of the soda from a date onward, ordered by date.
     *
     * @return list<Closure>
     */
    public function from(SodaId $sodaId, ClosureDate $date): array
    {
        $closures = array_filter(
            $this->closures,
            fn (Closure $closure): bool => $closure->sodaId->equals($sodaId) && ! $closure->date->isBefore($date),
        );

        usort($closures, fn (Closure $a, Closure $b): int => $a->date->value <=> $b->date->value);

        return $closures;
    }

    /** Remove the closure. */
    public function delete(Closure $closure): void
    {
        unset($this->closures[$closure->id->value]);
    }
}
