<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Src\Identity\Users\Domain\Contracts\PasswordHasher;

final class FakePasswordHasher implements PasswordHasher
{
    public int $checks = 0;

    public function hash(string $plainPassword): string
    {
        return 'hash:'.$plainPassword;
    }

    public function check(string $plainPassword, ?string $hash): bool
    {
        $this->checks++;

        return $hash !== null && $hash === $this->hash($plainPassword);
    }
}
