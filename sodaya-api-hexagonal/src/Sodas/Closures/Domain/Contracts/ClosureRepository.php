<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\Exceptions\ClosureAlreadyExistsException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;

interface ClosureRepository
{
    /** Generate the identity for a new closure. */
    public function nextId(): ClosureId;

    /**
     * Persist a new closure.
     *
     * @throws ClosureAlreadyExistsException
     */
    public function save(Closure $closure): void;

    /** Find a closure that belongs to the given soda. */
    public function find(ClosureId $id, SodaId $sodaId): ?Closure;

    /** Find the closure of the soda on a date, if any. */
    public function onDate(SodaId $sodaId, ClosureDate $date): ?Closure;

    /**
     * List one page of the closures of the soda from a date onward, ordered by date.
     *
     * @return Page<Closure>
     */
    public function from(SodaId $sodaId, ClosureDate $date, PageRequest $page): Page;

    /** Remove a closure. */
    public function delete(Closure $closure): void;
}
