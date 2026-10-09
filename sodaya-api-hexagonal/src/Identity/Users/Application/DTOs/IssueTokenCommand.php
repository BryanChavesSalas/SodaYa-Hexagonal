<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\DTOs;

final readonly class IssueTokenCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
    ) {}
}
