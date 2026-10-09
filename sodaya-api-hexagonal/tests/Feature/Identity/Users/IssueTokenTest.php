<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Database\Factories\UserFactory;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class IssueTokenTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** An active owner receives a token with the abilities of the role. */
    public function test_owner_logs_in_and_receives_role_abilities(): void
    {
        $user = UserModel::factory()->owner()->create([
            'email' => 'duena@sodaya.test',
        ]);

        $response = $this->postJson('/api/v1/tokens', [
            'correo' => 'duena@sodaya.test',
            'contrasena' => UserFactory::PASSWORD,
            'dispositivo' => 'Chrome en Windows',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'Bearer')
            ->assertJsonPath('data.abilities', Role::Owner->abilities())
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'tipo',
                    'abilities',
                ],
            ]);

        $this->assertNotSame('', $response->json('data.token'));

        $token = $user->tokens()->sole();

        $this->assertSame('Chrome en Windows', $token->name);
        $this->assertSame(Role::Owner->abilities(), $token->abilities);
    }

    /** Kitchen staff receives only the abilities of its role. */
    public function test_kitchen_logs_in_with_kitchen_abilities(): void
    {
        $user = UserModel::factory()->kitchen()->create([
            'email' => 'cocina@sodaya.test',
        ]);

        $response = $this->postJson('/api/v1/tokens', [
            'correo' => 'cocina@sodaya.test',
            'contrasena' => UserFactory::PASSWORD,
            'dispositivo' => 'Tablet cocina',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'Bearer')
            ->assertJsonPath('data.abilities', Role::Kitchen->abilities());

        $this->assertSame(
            Role::Kitchen->abilities(),
            $user->tokens()->sole()->abilities,
        );
    }

    /** A wrong password is indistinguishable from any other invalid credential. */
    public function test_wrong_password_returns_invalid_credentials(): void
    {
        UserModel::factory()->owner()->create([
            'email' => 'duena@sodaya.test',
        ]);

        $response = $this->postJson('/api/v1/tokens', [
            'correo' => 'duena@sodaya.test',
            'contrasena' => 'incorrecta',
            'dispositivo' => 'Chrome',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('status', 401)
            ->assertJsonPath(
                'type',
                rtrim((string) config('app.url'), '/').'/problemas/credenciales-invalidas',
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** An unknown email gives the same response as a wrong password. */
    public function test_unknown_email_returns_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/tokens', [
            'correo' => 'nadie@sodaya.test',
            'contrasena' => UserFactory::PASSWORD,
            'dispositivo' => 'Chrome',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('status', 401)
            ->assertJsonPath(
                'type',
                rtrim((string) config('app.url'), '/').'/problemas/credenciales-invalidas',
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** A disabled account cannot receive a token. */
    public function test_inactive_account_returns_invalid_credentials(): void
    {
        UserModel::factory()->owner()->inactive()->create([
            'email' => 'duena@sodaya.test',
        ]);

        $response = $this->postJson('/api/v1/tokens', [
            'correo' => 'duena@sodaya.test',
            'contrasena' => UserFactory::PASSWORD,
            'dispositivo' => 'Chrome',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonPath('status', 401)
            ->assertJsonPath(
                'type',
                rtrim((string) config('app.url'), '/').'/problemas/credenciales-invalidas',
            );

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** Malformed login data is rejected before authentication. */
    public function test_invalid_payload_returns_unprocessable_entity(): void
    {
        $response = $this->postJson('/api/v1/tokens', [
            'correo' => 'correo-invalido',
            'contrasena' => '',
            'dispositivo' => '',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonStructure([
                'errores' => [
                    'correo',
                    'contrasena',
                    'dispositivo',
                ],
            ]);
    }
}
