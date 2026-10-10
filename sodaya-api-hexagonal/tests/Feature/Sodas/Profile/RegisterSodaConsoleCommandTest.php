<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Profile;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class RegisterSodaConsoleCommandTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string COMMAND = 'sodaya:register-soda';

    /** The command is registered with the agreed arguments and options. */
    public function test_command_has_the_agreed_signature(): void
    {
        $command = Artisan::all()[self::COMMAND];
        $definition = $command->getDefinition();

        $this->assertSame(['name', 'owner-name', 'owner-email'], array_keys($definition->getArguments()));
        $this->assertSame(['payment-account', 'password'], array_keys($definition->getOptions()));
        $this->assertTrue($definition->getArgument('name')->isRequired());
        $this->assertTrue($definition->getArgument('owner-name')->isRequired());
        $this->assertTrue($definition->getArgument('owner-email')->isRequired());
        $this->assertTrue($definition->getOption('payment-account')->acceptValue());
        $this->assertTrue($definition->getOption('password')->acceptValue());
        $this->assertSame('Registra una soda y la cuenta de su dueño.', $command->getDescription());
    }

    /** A soda and its owner are registered from the options, and the password is not echoed. */
    public function test_soda_and_owner_are_registered_from_the_options(): void
    {
        $exitCode = Artisan::call(
            self::COMMAND,
            $this->arguments(['--password' => 'clave-segura', '--payment-account' => 'ID_DE_LA_CUENTA']),
        );

        $soda = SodaModel::query()->sole();
        $output = (string) preg_replace('/\s+/', ' ', Artisan::output());
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString("Soda registrada: Soda La Esquina ({$soda->id}). Dueño: ana@sodaya.test.", $output);
        $this->assertStringNotContainsString('clave-segura', $output);
        $this->assertSame('Soda La Esquina', $soda->name);
        $this->assertSame('ID_DE_LA_CUENTA', $soda->payment_account_id);

        $owner = UserModel::query()->sole();
        $this->assertSame('owner', $owner->role);
        $this->assertSame($soda->id, $owner->soda_id);
        $this->assertTrue(Hash::check('clave-segura', $owner->password));
    }

    /** Without --password the command asks for it once, without showing it. */
    public function test_password_is_asked_when_the_option_is_missing(): void
    {
        $this->artisan(self::COMMAND, $this->arguments())
            ->expectsQuestion('Contraseña del dueño', 'clave-segura')
            ->doesntExpectOutputToContain('clave-segura')
            ->assertExitCode(0);

        $this->assertNull(SodaModel::query()->sole()->payment_account_id);
        $this->assertTrue(Hash::check('clave-segura', UserModel::query()->sole()->password));
    }

    /** An invalid soda name is refused with its message and nothing is stored. */
    public function test_invalid_soda_name_is_refused(): void
    {
        $this->artisan(self::COMMAND, $this->arguments(['name' => '   ', '--password' => 'clave-segura']))
            ->expectsOutputToContain('El nombre de la soda es obligatorio y no puede superar los 120 caracteres.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('sodas', 0);
        $this->assertDatabaseCount('users', 0);
    }

    /** An empty payment account is refused with its message and nothing is stored. */
    public function test_empty_payment_account_is_refused(): void
    {
        $this->artisan(self::COMMAND, $this->arguments(['--password' => 'clave-segura', '--payment-account' => '']))
            ->expectsOutputToContain('El identificador de la cuenta de pago no puede estar vacío ni superar los 255 caracteres.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('sodas', 0);
        $this->assertDatabaseCount('users', 0);
    }

    /** A malformed owner email is refused and no soda is left behind. */
    public function test_invalid_owner_email_is_refused(): void
    {
        $this->artisan(self::COMMAND, $this->arguments(['owner-email' => 'ana.sodaya.test', '--password' => 'clave-segura']))
            ->expectsOutputToContain('El correo debe ser una dirección válida de 255 caracteres como máximo.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('sodas', 0);
        $this->assertDatabaseCount('users', 0);
    }

    /** A password shorter than eight characters is refused with its message and nothing is stored. */
    public function test_short_password_is_refused(): void
    {
        $this->artisan(self::COMMAND, $this->arguments(['--password' => '1234567']))
            ->expectsOutputToContain('La contraseña debe tener al menos 8 caracteres.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('sodas', 0);
        $this->assertDatabaseCount('users', 0);
    }

    /** An empty --password is refused without asking for another one. */
    public function test_empty_password_option_is_refused_without_asking(): void
    {
        $this->artisan(self::COMMAND, $this->arguments(['--password' => '']))
            ->expectsOutputToContain('La contraseña debe tener al menos 8 caracteres.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('sodas', 0);
    }

    /** A repeated owner email is refused with its message and keeps the first soda only. */
    public function test_repeated_owner_email_is_refused(): void
    {
        $this->artisan(self::COMMAND, $this->arguments(['--password' => 'clave-segura']))->assertExitCode(0);

        $this->artisan(self::COMMAND, $this->arguments(['name' => 'Soda El Parque', '--password' => 'clave-segura']))
            ->expectsOutputToContain('Ya existe una cuenta con ese correo.')
            ->assertExitCode(1);

        $this->assertSame(['Soda La Esquina'], SodaModel::query()->pluck('name')->all());
        $this->assertDatabaseCount('users', 1);
    }

    /** Without --password and without an interactive terminal the password is refused. */
    public function test_missing_password_without_a_terminal_is_refused(): void
    {
        $exitCode = Artisan::call(self::COMMAND, $this->arguments(['--no-interaction' => true]));

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('La contraseña debe tener al menos 8 caracteres.', Artisan::output());
        $this->assertDatabaseCount('sodas', 0);
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * Build the arguments of a valid call, overriding what a scenario changes.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function arguments(array $overrides = []): array
    {
        return [
            'name' => 'Soda La Esquina',
            'owner-name' => 'Ana Mora',
            'owner-email' => 'ana@sodaya.test',
            ...$overrides,
        ];
    }
}
