<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\UseCases;

use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepositoryContract;
use Src\Sodas\Closures\Domain\Exceptions\ClosureNotFoundException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;

final readonly class DeleteClosure
{
    public function __construct(
        private ClosureRepositoryContract $repository,
        private SodaContext $sodaContext
    ) {}

    public function execute(string $closureId): void
    {
        $sodaId = $this->sodaContext->current();
        $id = new ClosureId($closureId);

        if ($this->repository->findById($sodaId, $id) === null) {
            throw ClosureNotFoundException::create();
        }

        $this->repository->delete($sodaId, $id);
    }
}
