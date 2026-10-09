<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users\Application;

use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Application\DTOs\IssueTokenCommand;
use Src\Identity\Users\Application\UseCases\IssueToken;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\InvalidCredentialsException;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Identity\FakePasswordHasher;
use Tests\Support\Identity\FakeTokenIssuer;

final class IssueTokenTest extends TestCase
{
    /** Valid credentials issue a token with the abilities of the user's role. */
    public function test_valid_credentials_issue_a_token_with_role_abilities(): void
    {
        $user = $this->owner();

        $hasher = new FakePasswordHasher;
        $tokens = new FakeTokenIssuer;

        $useCase = new IssueToken(
            $this->repositoryWith($user),
            $hasher,
            $tokens,
        );

        $issued = $useCase->execute(
            new IssueTokenCommand(
                email: 'dueno@sodaya.test',
                password: 'secreto123',
                deviceName: 'Chrome Windows',
            ),
        );

        $this->assertSame('token-de-prueba', $issued->token);
        $this->assertSame(['cocina', 'administrar'], $issued->abilities);

        $this->assertTrue($user->id->equals($tokens->userId));
        $this->assertSame('Chrome Windows', $tokens->deviceName);
        $this->assertSame(['cocina', 'administrar'], $tokens->abilities);
        $this->assertSame(1, $hasher->checks);
    }

    /** A wrong password is rejected with invalid credentials. */
    public function test_wrong_password_is_rejected(): void
    {
        $hasher = new FakePasswordHasher;

        $useCase = new IssueToken(
            $this->repositoryWith($this->owner()),
            $hasher,
            new FakeTokenIssuer,
        );

        $this->expectException(InvalidCredentialsException::class);

        try {
            $useCase->execute(
                new IssueTokenCommand(
                    email: 'dueno@sodaya.test',
                    password: 'incorrecta',
                    deviceName: 'Chrome Windows',
                ),
            );
        } finally {
            $this->assertSame(1, $hasher->checks);
        }
    }

    /** A missing account still performs a password check before failing. */
    public function test_missing_account_still_checks_the_password(): void
    {
        $hasher = new FakePasswordHasher;

        $useCase = new IssueToken(
            $this->repositoryWith(null),
            $hasher,
            new FakeTokenIssuer,
        );

        $this->expectException(InvalidCredentialsException::class);

        try {
            $useCase->execute(
                new IssueTokenCommand(
                    email: 'noexiste@sodaya.test',
                    password: 'secreto123',
                    deviceName: 'Chrome Windows',
                ),
            );
        } finally {
            $this->assertSame(1, $hasher->checks);
        }
    }

    /** A disabled account is rejected even when its password is correct. */
    public function test_disabled_account_is_rejected(): void
    {
        $user = User::reconstitute(
            id: new UserId('0192f0c4-0000-7000-8000-000000000001'),
            name: new UserName('Dueño de soda'),
            email: new Email('dueno@sodaya.test'),
            passwordHash: 'hash:secreto123',
            role: Role::Owner,
            sodaId: new SodaId('0192f0c4-0000-7000-8000-000000000002'),
            active: false,
        );

        $hasher = new FakePasswordHasher;

        $useCase = new IssueToken(
            $this->repositoryWith($user),
            $hasher,
            new FakeTokenIssuer,
        );

        $this->expectException(InvalidCredentialsException::class);

        try {
            $useCase->execute(
                new IssueTokenCommand(
                    email: 'dueno@sodaya.test',
                    password: 'secreto123',
                    deviceName: 'Chrome Windows',
                ),
            );
        } finally {
            $this->assertSame(1, $hasher->checks);
        }
    }

    private function owner(): User
    {
        return User::create(
            id: new UserId('0192f0c4-0000-7000-8000-000000000001'),
            name: new UserName('Dueño de soda'),
            email: new Email('dueno@sodaya.test'),
            passwordHash: 'hash:secreto123',
            role: Role::Owner,
            sodaId: new SodaId('0192f0c4-0000-7000-8000-000000000002'),
        );
    }

    private function repositoryWith(?User $user): UserRepository
    {
        return new class($user) implements UserRepository
        {
            public function __construct(private ?User $user) {}

            public function nextId(): UserId
            {
                return new UserId('0192f0c4-0000-7000-8000-000000000003');
            }

            public function save(User $user): void
            {
                $this->user = $user;
            }

            public function findByEmail(Email $email): ?User
            {
                if ($this->user?->email->value !== $email->value) {
                    return null;
                }

                return $this->user;
            }
        };
    }
}
