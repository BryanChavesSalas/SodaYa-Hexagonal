<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class UserSchemaTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string CHECK_VIOLATION = '23514';

    /** The application role stores a customer, which is active by default. */
    public function test_application_role_stores_an_active_customer(): void
    {
        $this->insertUser('customer', null);

        $user = DB::table('users')->sole();

        $this->assertTrue(Str::isUuid($user->id, 7));
        $this->assertTrue($user->is_active);
        $this->assertNull($user->soda_id);
    }

    /** Staff accounts belong to a soda. */
    public function test_staff_accounts_belong_to_a_soda(): void
    {
        $sodaId = $this->insertSoda();

        $this->insertUser('kitchen', $sodaId, 'cocina@sodaya.test');
        $this->insertUser('owner', $sodaId, 'duena@sodaya.test');

        $this->assertSame(2, DB::table('users')->where('soda_id', $sodaId)->count());
    }

    /** The database rejects roles outside the list and a soda that does not match the role. */
    #[DataProvider('invalidAccounts')]
    public function test_database_rejects_invalid_accounts(string $role, bool $withSoda, string $constraint): void
    {
        try {
            $this->insertUser($role, $withSoda ? $this->insertSoda() : null);
            $this->fail('The database accepted an invalid account.');
        } catch (QueryException $exception) {
            $this->assertSame(self::CHECK_VIOLATION, $exception->getCode());
            $this->assertStringContainsString($constraint, $exception->getMessage());
        }
    }

    /**
     * Accounts that break a rule of the table and the constraint that stops them.
     *
     * @return array<string, array{string, bool, string}>
     */
    public static function invalidAccounts(): array
    {
        return [
            'unknown role' => ['admin', false, 'users_role_check'],
            'kitchen without soda' => ['kitchen', false, 'users_soda_id_check'],
            'owner without soda' => ['owner', false, 'users_soda_id_check'],
            'customer with soda' => ['customer', true, 'users_soda_id_check'],
        ];
    }

    /** Two accounts cannot share an email address. */
    public function test_database_rejects_a_repeated_email(): void
    {
        $this->insertUser('customer', null);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertUser('owner', $this->insertSoda());
    }

    /** Deleting a soda deletes the accounts of its staff. */
    public function test_deleting_a_soda_deletes_its_staff(): void
    {
        $sodaId = $this->insertSoda();
        $this->insertUser('owner', $sodaId);

        DB::table('sodas')->where('id', $sodaId)->delete();

        $this->assertDatabaseCount('users', 0);
    }

    /** Insert a soda and return its identifier. */
    private function insertSoda(): string
    {
        $id = (string) Str::uuid7();

        DB::table('sodas')->insert(['id' => $id, 'name' => 'Soda de prueba']);

        return $id;
    }

    /** Insert an account straight into the table. */
    private function insertUser(string $role, ?string $sodaId, string $email = 'ana@sodaya.test'): void
    {
        DB::table('users')->insert([
            'name' => 'Ana Mora',
            'email' => $email,
            'password' => 'hash',
            'role' => $role,
            'soda_id' => $sodaId,
        ]);
    }
}
