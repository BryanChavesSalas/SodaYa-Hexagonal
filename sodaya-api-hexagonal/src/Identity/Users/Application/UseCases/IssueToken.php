<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\UseCases;

use Src\Identity\Users\Application\DTOs\IssuedToken;
use Src\Identity\Users\Application\DTOs\IssueTokenCommand;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\TokenIssuer;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Exceptions\InvalidCredentialsException;
use Src\Identity\Users\Domain\ValueObjects\Email;

final readonly class IssueToken
{
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private TokenIssuer $tokens,
    ) {}

    /** Issue a token when the supplied credentials belong to an active account. */
    public function execute(IssueTokenCommand $command): IssuedToken
    {
        $user = $this->users->findByEmail(new Email($command->email));

        $passwordMatches = $this->passwordHasher->check(
            $command->password,
            $user?->passwordHash,
        );

        if (
            $user === null
            || ! $user->active
            || ! $passwordMatches
        ) {
            throw InvalidCredentialsException::create();
        }

        $abilities = $user->role->abilities();

        return new IssuedToken(
            $this->tokens->issue(
                $user->id,
                $command->deviceName,
                $abilities,
            ),
            $abilities,
        );
    }
}
