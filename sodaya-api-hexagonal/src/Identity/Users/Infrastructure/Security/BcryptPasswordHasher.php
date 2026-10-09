<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Security;

use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;

final readonly class BcryptPasswordHasher implements PasswordHasher
{
    private const string DRIVER = 'bcrypt';

    /** Hash the password with bcrypt and the configured number of rounds. */
    public function hash(string $plainPassword): string
    {
        return Hash::driver(self::DRIVER)->make($plainPassword);
    }

    /** Compare the password with a bcrypt hash in constant time. */
    public function check(string $plainPassword, string $hash): bool
    {
        return Hash::driver(self::DRIVER)->check($plainPassword, $hash);
    }
}
