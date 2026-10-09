<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Security;

use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;

final readonly class BcryptPasswordHasher implements PasswordHasher
{
    private const string DRIVER = 'bcrypt';

    /**
     * Dummy bcrypt hash used when no stored password exists.
     *
     * This keeps the authentication work similar whether the email exists or not.
     */
    private const string DUMMY_HASH = '$2y$12$4OyXqU6iV7xnbx4cmySfBeMWPgymFDHB8cLJXcLQQD9F2SwQpJYSe';

    /** Hash the password with bcrypt and the configured number of rounds. */
    public function hash(string $plainPassword): string
    {
        return Hash::driver(self::DRIVER)->make($plainPassword);
    }

    /** Compare the password with a bcrypt hash while also doing the work when none exists. */
    public function check(string $plainPassword, ?string $hash): bool
    {
        $matches = Hash::driver(self::DRIVER)->check(
            $plainPassword,
            $hash ?? self::DUMMY_HASH,
        );

        return $hash !== null && $matches;
    }
}
