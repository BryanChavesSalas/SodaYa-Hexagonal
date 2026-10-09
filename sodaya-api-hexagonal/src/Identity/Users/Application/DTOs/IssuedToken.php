<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\DTOs;

final readonly class IssuedToken
{
    /**
     * @param  list<string>  $abilities
     */
    public function __construct(
        public string $token,
        public array $abilities,
    ) {}
}
