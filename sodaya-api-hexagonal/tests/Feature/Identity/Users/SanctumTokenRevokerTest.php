<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Identity\Users\Infrastructure\Security\SanctumTokenRevoker;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class SanctumTokenRevokerTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** Revoking one token deletes only that session and leaves the others of the user open. */
    public function test_revokes_one_token_of_the_user(): void
    {
        $user = UserModel::factory()->owner()->create();
        $closed = $user->createToken('Chrome', Role::Owner->abilities())->accessToken;
        $open = $user->createToken('Teléfono', Role::Owner->abilities())->accessToken;

        new SanctumTokenRevoker()->revoke(new UserId($user->id), (string) $closed->getKey());

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $closed->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $open->getKey()]);
    }

    /** The id of a token that belongs to someone else deletes nothing. */
    public function test_ignores_a_token_of_another_person(): void
    {
        $user = UserModel::factory()->owner()->create();
        $user->createToken('Chrome', Role::Owner->abilities());
        $stranger = UserModel::factory()->kitchen()->create();
        $foreign = $stranger->createToken('Tablet cocina', Role::Kitchen->abilities())->accessToken;

        new SanctumTokenRevoker()->revoke(new UserId($user->id), (string) $foreign->getKey());

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->getKey()]);
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    /** Revoking all tokens closes every session of the user and no one else's. */
    public function test_revokes_every_token_of_the_user(): void
    {
        $user = UserModel::factory()->owner()->create();
        $user->createToken('Chrome', Role::Owner->abilities());
        $user->createToken('Teléfono', Role::Owner->abilities());
        $stranger = UserModel::factory()->kitchen()->create();
        $kept = $stranger->createToken('Tablet cocina', Role::Kitchen->abilities())->accessToken;

        new SanctumTokenRevoker()->revokeAll(new UserId($user->id));

        $this->assertCount(0, $user->tokens()->get());
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $kept->getKey()]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
