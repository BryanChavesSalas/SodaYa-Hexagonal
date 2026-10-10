<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Profile;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Sodas\Profile\Application\DTOs\RegisterSodaCommand;
use Src\Sodas\Profile\Application\UseCases\RegisterSoda;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class RegisterSodaTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** @var list<string> */
    private array $statements = [];

    private RegisterSoda $registerSoda;

    /** Resolve the use case with its real adapters and record every statement. */
    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function (QueryExecuted $query): void {
            $this->statements[] = $query->sql;
        });

        $this->registerSoda = $this->app->make(RegisterSoda::class);
    }

    /** The soda and its owner are stored together, with the owner bound to the soda. */
    public function test_soda_and_owner_are_stored_together(): void
    {
        $soda = $this->registerSoda->execute($this->command(paymentAccountId: 'acct_123'));

        $model = SodaModel::query()->findOrFail($soda->id->value);
        $this->assertSame('Soda La Esquina', $model->name);
        $this->assertSame('acct_123', $model->payment_account_id);

        $owner = UserModel::query()->where('email', 'ana@sodaya.test')->firstOrFail();
        $this->assertSame('Ana Mora', $owner->name);
        $this->assertSame(Role::Owner->value, $owner->role);
        $this->assertSame($soda->id->value, $owner->soda_id);
        $this->assertTrue($owner->is_active);
        $this->assertNotSame('clave-segura', $owner->password);
        $this->assertTrue(Hash::check('clave-segura', $owner->password));
    }

    /** A repeated owner email leaves no second soda behind. */
    public function test_repeated_owner_email_leaves_no_soda_behind(): void
    {
        $this->registerSoda->execute($this->command());
        $this->statements = [];

        try {
            $this->registerSoda->execute($this->command(name: 'Soda El Parque'));
            $this->fail('A repeated owner email was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('identity.email_already_registered', $exception->translationKey());
        }

        $this->assertSodaInsertWasRolledBack(sodasKept: 1);
        $this->assertDatabaseCount('users', 1);
    }

    /** A short owner password leaves neither a soda nor an account behind. */
    public function test_short_owner_password_leaves_nothing_behind(): void
    {
        try {
            $this->registerSoda->execute($this->command(ownerPassword: '1234567'));
            $this->fail('A short owner password was accepted.');
        } catch (InvalidValueException $exception) {
            $this->assertSame('identity.password_too_short', $exception->translationKey());
        }

        $this->assertSodaInsertWasRolledBack(sodasKept: 0);
        $this->assertDatabaseCount('users', 0);
    }

    /** Assert the soda was inserted during the attempt and is gone after the rollback. */
    private function assertSodaInsertWasRolledBack(int $sodasKept): void
    {
        $this->assertTrue(
            array_any($this->statements, fn (string $sql): bool => str_starts_with($sql, 'insert into "sodas"')),
            'The soda was never inserted, so the rollback proves nothing.',
        );
        $this->assertDatabaseCount('sodas', $sodasKept);
    }

    /** Build the command of a soda, overriding only what a scenario changes. */
    private function command(
        string $name = 'Soda La Esquina',
        string $ownerPassword = 'clave-segura',
        ?string $paymentAccountId = null,
    ): RegisterSodaCommand {
        return new RegisterSodaCommand($name, 'Ana Mora', 'ana@sodaya.test', $ownerPassword, $paymentAccountId);
    }
}
