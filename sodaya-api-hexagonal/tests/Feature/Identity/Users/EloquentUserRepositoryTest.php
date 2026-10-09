<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserName;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Identity\Users\Infrastructure\Persistence\Repositories\EloquentUserRepository;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private EloquentUserRepository $repository;

    /** Create the repository every scenario works with. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentUserRepository;
    }

    /** A saved staff account is read back unchanged by its email. */
    public function test_saved_account_is_read_back_unchanged(): void
    {
        $user = $this->owner('duena@sodaya.test');

        $this->repository->save($user);

        $this->assertEquals($user, $this->repository->findByEmail(new Email('duena@sodaya.test')));
    }

    /** The lookup ignores the spelling the person types. */
    public function test_lookup_ignores_case_and_spaces(): void
    {
        $this->repository->save($this->owner('duena@sodaya.test'));

        $this->assertNotNull($this->repository->findByEmail(new Email('  Duena@SodaYa.test ')));
    }

    /** An unknown email finds nothing. */
    public function test_unknown_email_finds_nothing(): void
    {
        UserModel::factory()->create();

        $this->assertNull($this->repository->findByEmail(new Email('nadie@sodaya.test')));
    }

    /** The stored password is the hash the aggregate carries and stays hidden from serialization. */
    public function test_password_is_stored_as_given_and_hidden(): void
    {
        $user = $this->owner('duena@sodaya.test');

        $this->repository->save($user);

        $model = UserModel::query()->sole();
        $this->assertSame($user->passwordHash, $model->password);
        $this->assertArrayNotHasKey('password', $model->toArray());
    }

    /** The unique key surfaces as the domain error and keeps the transaction usable. */
    public function test_repeated_email_raises_the_domain_error(): void
    {
        $this->repository->save($this->owner('duena@sodaya.test'));

        try {
            $this->repository->save($this->owner('duena@sodaya.test'));
            $this->fail('A second account with the same email was accepted.');
        } catch (EmailAlreadyRegisteredException) {
            $this->assertDatabaseCount('users', 1);
        }
    }

    /** Build the owner of a new soda with a fresh identity. */
    private function owner(string $email): User
    {
        return User::create(
            $this->repository->nextId(),
            new UserName('Doña Ana'),
            new Email($email),
            password_hash('secreto123', PASSWORD_BCRYPT, ['cost' => 4]),
            Role::Owner,
            new SodaId(SodaModel::factory()->create()->id),
        );
    }
}
