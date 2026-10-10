<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Src\Identity\Users\Domain\Contracts\PasswordHasher;

final class FakePasswordHasher implements PasswordHasher
{
    private const string PREFIX = 'hashed:';

    /** Mark the password as hashed without using a real algorithm. */
    public function hash(string $plainPassword): string
    {
        return self::PREFIX.$plainPassword;
    }

    /** Compare the password with a hash made by this fake. */
    public function check(string $plainPassword, string $hash): bool
    {
        return $hash === self::PREFIX.$plainPassword;
    }
}
