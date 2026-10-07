<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\UseCases;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepository;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;

final readonly class ListClosures
{
    /** Receive the closure repository port. */
    public function __construct(private ClosureRepository $closures) {}

    /**
     * List the closures of the soda from today onward, ordered by date.
     *
     * @return list<Closure>
     */
    public function execute(string $sodaId, DateTimeImmutable $now): array
    {
        return $this->closures->from(new SodaId($sodaId), ClosureDate::fromMoment($now));
    }
}
