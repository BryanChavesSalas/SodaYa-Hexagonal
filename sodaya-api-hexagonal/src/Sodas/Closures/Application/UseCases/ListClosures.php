<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\UseCases;

use DateTimeImmutable;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepository;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;

final readonly class ListClosures
{
    /** Receive the closure repository port. */
    public function __construct(private ClosureRepository $closures) {}

    /**
     * List one page of the closures of the soda from today onward, ordered by date.
     *
     * @return Page<Closure>
     */
    public function execute(string $sodaId, DateTimeImmutable $now, PageRequest $page): Page
    {
        return $this->closures->from(new SodaId($sodaId), ClosureDate::fromMoment($now), $page);
    }
}
