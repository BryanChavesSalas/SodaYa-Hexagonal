<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\UseCases;

use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\Closures\Application\DTOs\RegisterClosureCommand;
use Src\Sodas\Closures\Domain\Closure;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepositoryContract;
use Src\Sodas\Closures\Domain\Exceptions\ClosureAlreadyExistsException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

final readonly class RegisterClosure
{
    public function __construct(
        private ClosureRepositoryContract $repository,
        private SodaContext $sodaContext
    ) {}

    public function execute(RegisterClosureCommand $command): Closure
    {
        $sodaId = $this->sodaContext->current();
        $date = new ClosureDate($command->date);

        if ($this->repository->existsForDate($sodaId, $date)) {
            throw ClosureAlreadyExistsException::create();
        }

        $closure = Closure::create(
            $this->repository->nextId(),
            $sodaId,
            $date,
            new ClosureReason($command->reason)
        );

        $this->repository->save($closure);

        return $closure;
    }
}
