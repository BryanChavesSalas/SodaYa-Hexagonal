<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CloseSessionTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string CURRENT = '/api/v1/tokens/actual';

    private const string ALL = '/api/v1/tokens';

    /** The token in use is revoked and the other sessions of the person stay open. */
    public function test_revokes_the_token_in_use(): void
    {
        $user = UserModel::factory()->owner()->create();
        $inUse = $user->createToken('Chrome en Windows', Role::Owner->abilities());
        $other = $user->createToken('Teléfono', Role::Owner->abilities());

        $this->withToken($inUse->plainTextToken)
            ->deleteJson(self::CURRENT)
            ->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $inUse->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    /** Closing every session removes all the tokens of the person and only theirs. */
    public function test_revokes_all_the_tokens_of_the_user(): void
    {
        $user = UserModel::factory()->owner()->create();
        $current = $user->createToken('Chrome', Role::Owner->abilities());
        $user->createToken('Teléfono', Role::Owner->abilities());
        $user->createToken('Tablet', Role::Owner->abilities());

        $stranger = UserModel::factory()->kitchen()->create();
        $stranger->createToken('Tablet cocina', Role::Kitchen->abilities());

        $this->withToken($current->plainTextToken)
            ->deleteJson(self::ALL)
            ->assertNoContent();

        $this->assertCount(0, $user->tokens()->get());
        $this->assertCount(1, $stranger->tokens()->get());
    }

    /** A revoked token is answered with a 401 problem document. */
    public function test_revoked_token_is_unauthenticated(): void
    {
        $user = UserModel::factory()->owner()->create();
        $token = $user->createToken('Chrome', Role::Owner->abilities())->plainTextToken;

        $this->withToken($token)->deleteJson(self::CURRENT)->assertNoContent();

        // The guard keeps the user in memory inside one test; forget it to behave like a new request.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->deleteJson(self::ALL)
            ->assertUnauthorized()
            ->assertJsonPath('status', 401)
            ->assertJsonPath(
                'type',
                rtrim((string) config('app.url'), '/').'/problemas/no-autenticado',
            );
    }

    /** Without a token neither endpoint is reachable. */
    public function test_requires_authentication(): void
    {
        $this->deleteJson(self::CURRENT)->assertUnauthorized();
        $this->deleteJson(self::ALL)->assertUnauthorized();
    }
}
