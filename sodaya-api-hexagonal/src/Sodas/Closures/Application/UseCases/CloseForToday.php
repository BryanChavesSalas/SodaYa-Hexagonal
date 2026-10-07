<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\UseCases;

use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\Closures\Domain\Closure;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepositoryContract;
use Src\Sodas\Closures\Domain\Exceptions\ClosureAlreadyExistsException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

final readonly class CloseForToday
{
    public function __construct(
        private ClosureRepositoryContract $repository,
        private SodaContext $sodaContext
    ) {}

    public function execute(?string $reason = null): Closure
    {
        $sodaId = $this->sodaContext->current();
        $today = ClosureDate::today();

        if ($this->repository->existsForDate($sodaId, $today)) {
            throw ClosureAlreadyExistsException::create();
        }

        $closure = Closure::create(
            $this->repository->nextId(),
            $sodaId,
            $today,
            new ClosureReason($reason)
        );

        $this->repository->save($closure);

        return $closure;
    }
}
