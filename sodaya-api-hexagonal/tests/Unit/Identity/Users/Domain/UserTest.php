<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;

final class UserTest extends TestCase
{
    private const string USER_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44';

    private const string SODA_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    /** A new staff account belongs to its soda and starts active. */
    #[DataProvider('staffRoles')]
    public function test_new_staff_account_belongs_to_its_soda(Role $role): void
    {
        $user = $this->user($role, new SodaId(self::SODA_ID));

        $this->assertSame(self::SODA_ID, $user->sodaId?->value);
        $this->assertSame($role, $user->role);
        $this->assertTrue($user->active);
    }

    /** A staff account without a soda is rejected. */
    #[DataProvider('staffRoles')]
    public function test_staff_account_requires_a_soda(Role $role): void
    {
        try {
            $this->user($role, null);
            $this->fail('A staff account without a soda was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('identity.staff_without_soda', $exception->translationKey());
        }
    }

    /**
     * Roles that belong to the staff of a soda.
     *
     * @return array<string, array{Role}>
     */
    public static function staffRoles(): array
    {
        return [
            'kitchen' => [Role::Kitchen],
            'owner' => [Role::Owner],
        ];
    }

    /** A customer account never belongs to a soda. */
    public function test_customer_account_has_no_soda(): void
    {
        $this->assertNull($this->user(Role::Customer, null)->sodaId);

        try {
            $this->user(Role::Customer, new SodaId(self::SODA_ID));
            $this->fail('A customer account with a soda was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('identity.customer_with_soda', $exception->translationKey());
        }
    }

    /** Each role grants the abilities of the closed list. */
    public function test_each_role_grants_its_abilities(): void
    {
        $this->assertSame(['pedidos'], Role::Customer->abilities());
        $this->assertSame(['cocina'], Role::Kitchen->abilities());
        $this->assertSame(['cocina', 'administrar'], Role::Owner->abilities());
    }

    /** A stored account keeps its state, even when it was deactivated. */
    public function test_reconstitute_keeps_the_stored_state(): void
    {
        $user = User::reconstitute(
            new UserId(self::USER_ID),
            new UserName('Ana Mora'),
            new Email('ana@sodaya.test'),
            'stored-hash',
            Role::Kitchen,
            new SodaId(self::SODA_ID),
            false,
        );

        $this->assertSame('stored-hash', $user->passwordHash);
        $this->assertFalse($user->active);
    }

    /** Build an account with the given role and soda. */
    private function user(Role $role, ?SodaId $sodaId): User
    {
        return User::create(
            new UserId(self::USER_ID),
            new UserName('Ana Mora'),
            new Email('ana@sodaya.test'),
            'hash',
            $role,
            $sodaId,
        );
    }
}
