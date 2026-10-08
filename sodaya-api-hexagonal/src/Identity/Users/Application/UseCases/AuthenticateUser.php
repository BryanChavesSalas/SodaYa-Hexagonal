<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\UseCases;

use Src\Identity\Users\Domain\Contracts\AccessTokenIssuer;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Exceptions\InvalidCredentialsException;
use Src\Identity\Users\Domain\ValueObjects\Email;

final readonly class AuthenticateUser
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private AccessTokenIssuer $tokens,
    ) {}

    /** Authenticate an active account and issue a token with its role abilities. */
    public function execute(
        string $email,
        string $password,
        string $deviceName,
    ): string {
        $user = $this->users->findByEmail(new Email($email));

        if (
            $user === null
            || ! $user->active
            || ! $this->passwordHasher->check($password, $user->passwordHash)
        ) {
            throw InvalidCredentialsException::create();
        }

        return $this->tokens->issue(
            $user,
            $deviceName,
            $user->role->abilities(),
        );
    }
}
