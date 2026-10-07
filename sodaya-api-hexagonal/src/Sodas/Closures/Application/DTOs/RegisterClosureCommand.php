<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Application\DTOs;

final readonly class RegisterClosureCommand
{
    public function __construct(
        public string $date,
        public ?string $reason = null
    ) {}
}
