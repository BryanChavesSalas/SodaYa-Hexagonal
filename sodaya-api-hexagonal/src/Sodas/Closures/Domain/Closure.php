<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Domain;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

final class Closure
{
    public function __construct(
        private readonly ClosureId $id,
        private readonly SodaId $sodaId,
        private readonly ClosureDate $date,
        private readonly ClosureReason $reason
    ) {}

    public static function create(
        ClosureId $id,
        SodaId $sodaId,
        ClosureDate $date,
        ClosureReason $reason
    ): self {
        return new self($id, $sodaId, $date, $reason);
    }

    public function id(): ClosureId
    {
        return $this->id;
    }

    public function sodaId(): SodaId
    {
        return $this->sodaId;
    }

    public function date(): ClosureDate
    {
        return $this->date;
    }

    public function reason(): ClosureReason
    {
        return $this->reason;
    }
}
