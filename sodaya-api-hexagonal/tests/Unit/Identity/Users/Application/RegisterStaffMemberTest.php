<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users\Application;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Application\DTOs\RegisterStaffMemberCommand;
use Src\Identity\Users\Application\UseCases\RegisterStaffMember;
use Src\Identity\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Tests\Support\Identity\FakePasswordHasher;
use Tests\Support\Identity\InMemoryUserRepository;

final class RegisterStaffMemberTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private InMemoryUserRepository $users;

    private RegisterStaffMember $registerStaffMember;

    /** Wire the use case to in-memory ports. */
    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository;
        $this->registerStaffMember = new RegisterStaffMember($this->users, new FakePasswordHasher);
    }

    /** An owner is registered as an active account of the soda with a hashed password. */
    public function test_owner_is_registered_with_a_hashed_password(): void
    {
        $user = $this->registerStaffMember->execute($this->command(role: Role::Owner));

        $this->assertSame('Ana Mora', $user->name->value);
        $this->assertSame('ana@sodaya.test', $user->email->value);
        $this->assertSame(Role::Owner, $user->role);
        $this->assertSame(self::SODA_ID, $user->sodaId?->value);
        $this->assertTrue($user->active);
        $this->assertSame('hashed:clave-segura', $user->passwordHash);
        $this->assertEquals($user, $this->users->findByEmail(new Email('ana@sodaya.test')));
    }

    /** A kitchen member is registered with the role it was given. */
    public function test_kitchen_member_is_registered(): void
    {
        $user = $this->registerStaffMember->execute($this->command(role: Role::Kitchen));

        $this->assertSame(Role::Kitchen, $user->role);
    }

    /** A second account with the same email is refused and the first one is kept. */
    public function test_repeated_email_is_refused(): void
    {
        $this->registerStaffMember->execute($this->command());

        $this->expectException(EmailAlreadyRegisteredException::class);

        try {
            $this->registerStaffMember->execute($this->command(email: ' ANA@sodaya.test '));
        } finally {
            $this->assertSame(1, $this->users->count());
        }
    }

    /** A password shorter than the minimum is refused before anything is stored. */
    public function test_short_password_is_refused(): void
    {
        try {
            $this->registerStaffMember->execute($this->command(password: '1234567'));
            $this->fail('A short password was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('identity.password_too_short', $exception->translationKey());
            $this->assertSame(0, $this->users->count());
        }
    }

    /** A customer cannot be registered as staff of a soda. */
    public function test_customer_role_is_refused(): void
    {
        try {
            $this->registerStaffMember->execute($this->command(role: Role::Customer));
            $this->fail('A customer was registered as staff.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('identity.customer_with_soda', $exception->translationKey());
            $this->assertSame(0, $this->users->count());
        }
    }

    /** A blank name or a malformed email is refused before anything is stored. */
    #[DataProvider('invalidIdentities')]
    public function test_invalid_name_or_email_is_refused(string $name, string $email, string $key): void
    {
        try {
            $this->registerStaffMember->execute($this->command(name: $name, email: $email));
            $this->fail('An invalid account was registered.');
        } catch (InvalidValueException $exception) {
            $this->assertSame($key, $exception->translationKey());
            $this->assertSame(0, $this->users->count());
        }
    }

    /**
     * Names and emails that break their invariants.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function invalidIdentities(): array
    {
        return [
            'blank name' => ['   ', 'ana@sodaya.test', 'identity.name_invalid'],
            'malformed email' => ['Ana Mora', 'ana.sodaya.test', 'identity.email_invalid'],
        ];
    }

    /** Build the command of an owner, overriding only what a scenario changes. */
    private function command(
        string $name = 'Ana Mora',
        string $email = 'ana@sodaya.test',
        string $password = 'clave-segura',
        Role $role = Role::Owner,
    ): RegisterStaffMemberCommand {
        return new RegisterStaffMemberCommand($name, $email, $password, self::SODA_ID, $role);
    }
}
