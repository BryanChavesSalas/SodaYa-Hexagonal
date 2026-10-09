<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\UseCases;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepository;
use Src\Sodas\Closures\Domain\Exceptions\ClosureNotFoundException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;

final readonly class DeleteClosure
{
    /** Receive the closure repository port. */
    public function __construct(private ClosureRepository $closures) {}

    /** Remove a closure of the soda so it opens again that day. */
    public function execute(string $closureId, string $sodaId): void
    {
        $closure = $this->closures->find(new ClosureId($closureId), new SodaId($sodaId))
            ?? throw ClosureNotFoundException::create();

        $this->closures->delete($closure);
    }
}
