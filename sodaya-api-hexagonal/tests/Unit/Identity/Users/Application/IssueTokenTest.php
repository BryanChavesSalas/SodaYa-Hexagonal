<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Application\DTOs\IssuedToken;
use Src\Identity\Users\Application\DTOs\IssueTokenCommand;
use Src\Identity\Users\Application\UseCases\IssueToken;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\InvalidCredentialsException;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Identity\FakePasswordHasher;
use Tests\Support\Identity\FakeTokenIssuer;
use Tests\Support\Identity\InMemoryUserRepository;

final class IssueTokenTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private InMemoryUserRepository $users;

    private FakePasswordHasher $passwords;

    private FakeTokenIssuer $tokens;

    /** Start every scenario without accounts nor tokens. */
    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository;
        $this->passwords = new FakePasswordHasher;
        $this->tokens = new FakeTokenIssuer;
    }

    /** Valid credentials get a token for the device with the abilities of the role. */
    #[DataProvider('roles')]
    public function test_issues_a_token_with_the_abilities_of_the_role(Role $role, ?string $sodaId, array $abilities): void
    {
        $user = $this->account($role, $sodaId);

        $token = $this->login(' ANA@sodaya.test ', 'secreto123');

        $this->assertSame('1|token-de-prueba', $token->plainTextToken);
        $this->assertSame($abilities, $token->abilities);
        $this->assertSame([[$user->id->value, 'Tablet de la cocina', $abilities]], $this->tokens->issued);
    }

    /**
     * Roles, the soda each one needs and the abilities of its tokens.
     *
     * @return array<string, array{Role, string|null, list<string>}>
     */
    public static function roles(): array
    {
        return [
            'customer' => [Role::Customer, null, ['pedidos']],
            'kitchen' => [Role::Kitchen, self::SODA_ID, ['cocina']],
            'owner' => [Role::Owner, self::SODA_ID, ['cocina', 'administrar']],
        ];
    }

    /** A wrong password, an unknown email and an inactive account fail in the same way. */
    #[DataProvider('invalidCredentials')]
    public function test_rejects_invalid_credentials_without_saying_why(string $email, string $password, bool $active): void
    {
        $this->account(Role::Owner, self::SODA_ID, $active);

        try {
            $this->login($email, $password);
            $this->fail('Invalid credentials received a token.');
        } catch (InvalidCredentialsException $exception) {
            $this->assertSame('identity.invalid_credentials', $exception->translationKey());
            $this->assertSame([], $this->tokens->issued);
        }
    }

    /**
     * Credentials that must not receive a token.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function invalidCredentials(): array
    {
        return [
            'wrong password' => ['ana@sodaya.test', 'otraclave456', true],
            'unknown email' => ['nadie@sodaya.test', 'secreto123', true],
            'inactive account' => ['ana@sodaya.test', 'secreto123', false],
        ];
    }

    /** An unknown email still runs one password check, so the time does not reveal which emails exist. */
    public function test_unknown_email_still_checks_a_password(): void
    {
        try {
            $this->login('nadie@sodaya.test', 'secreto123');
            $this->fail('An unknown email received a token.');
        } catch (InvalidCredentialsException) {
            $this->assertSame(1, $this->passwords->checks);
        }
    }

    /** Store an account whose password is "secreto123". */
    private function account(Role $role, ?string $sodaId, bool $active = true): User
    {
        $user = User::reconstitute(
            $this->users->nextId(),
            new UserName('Ana Mora'),
            new Email('ana@sodaya.test'),
            $this->passwords->hash('secreto123'),
            $role,
            $sodaId === null ? null : new SodaId($sodaId),
            $active,
        );

        $this->users->save($user);

        return $user;
    }

    /** Log in from the kitchen tablet through the use case. */
    private function login(string $email, string $password): IssuedToken
    {
        return new IssueToken($this->users, $this->passwords, $this->tokens)->execute(
            new IssueTokenCommand($email, $password, 'Tablet de la cocina'),
        );
    }
}
