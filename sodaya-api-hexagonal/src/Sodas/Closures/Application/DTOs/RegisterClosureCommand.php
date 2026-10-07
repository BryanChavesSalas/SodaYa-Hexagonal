<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\DTOs;

final readonly class RegisterClosureCommand
{
    /** Carry the data needed to register a closure. */
    public function __construct(
        public string $sodaId,
        public string $date,
        public ?string $reason,
    ) {}
}
