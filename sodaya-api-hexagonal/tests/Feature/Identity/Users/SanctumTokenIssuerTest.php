<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Laravel\Sanctum\PersonalAccessToken;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Identity\Users\Infrastructure\Security\SanctumTokenIssuer;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class SanctumTokenIssuerTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** The token is stored as a hash, named after the device and limited to the given abilities. */
    public function test_issued_token_is_stored_hashed_with_its_abilities(): void
    {
        $user = UserModel::factory()->kitchen()->create();

        $plainTextToken = new SanctumTokenIssuer()->issue(new UserId($user->id), 'Tablet de la cocina', ['cocina']);

        [$id, $secret] = explode('|', $plainTextToken, 2);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $id,
            'tokenable_id' => $user->id,
            'name' => 'Tablet de la cocina',
            'token' => hash('sha256', $secret),
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $secret]);

        $token = PersonalAccessToken::findToken($plainTextToken);
        $this->assertNotNull($token);
        $this->assertTrue($token->can('cocina'));
        $this->assertFalse($token->can('administrar'));
    }

    /** Each login creates its own token, so every device can be closed on its own. */
    public function test_every_device_gets_its_own_token(): void
    {
        $user = UserModel::factory()->owner()->create();
        $issuer = new SanctumTokenIssuer;

        $first = $issuer->issue(new UserId($user->id), 'Celular', ['cocina', 'administrar']);
        $second = $issuer->issue(new UserId($user->id), 'Computadora', ['cocina', 'administrar']);

        $this->assertNotSame($first, $second);
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }
}
