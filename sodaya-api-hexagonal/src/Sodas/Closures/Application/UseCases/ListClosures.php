<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\UseCases;

use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\Closures\Domain\Closure;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepositoryContract;

final readonly class ListClosures
{
    public function __construct(
        private ClosureRepositoryContract $repository,
        private SodaContext $sodaContext
    ) {}

    /** @return array<int, Closure> */
    public function execute(): array
    {
        return $this->repository->allForSoda($this->sodaContext->current());
    }
}
