<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain\Entities;

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

final readonly class Closure
{
    /** Hold the full state of an exceptional closure. */
    private function __construct(
        public ClosureId $id,
        public SodaId $sodaId,
        public ClosureDate $date,
        public ?ClosureReason $reason,
    ) {}

    /** Register a closure for today or a later date. */
    public static function create(
        ClosureId $id,
        SodaId $sodaId,
        ClosureDate $date,
        ?ClosureReason $reason,
        ClosureDate $today,
    ): self {
        if ($date->isBefore($today)) {
            throw new InvalidValueException('sodas.closure_date_in_past');
        }

        return new self($id, $sodaId, $date, $reason);
    }

    /** Rebuild a closure from its stored state, even if its date already passed. */
    public static function reconstitute(
        ClosureId $id,
        SodaId $sodaId,
        ClosureDate $date,
        ?ClosureReason $reason,
    ): self {
        return new self($id, $sodaId, $date, $reason);
    }
}
