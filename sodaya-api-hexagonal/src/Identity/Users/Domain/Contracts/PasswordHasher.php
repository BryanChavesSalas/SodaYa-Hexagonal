<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Contracts;

interface PasswordHasher
{
    /** Turn a plain password into an adaptive one-way hash. */
    public function hash(string $plainPassword): string;

    /** Tell whether a plain password matches a stored hash. */
    public function check(string $plainPassword, ?string $hash): bool;
}
