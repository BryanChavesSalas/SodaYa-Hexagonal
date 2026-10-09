<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Identity\Users\Infrastructure\Security\SanctumTokenIssuer;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class SanctumTokenIssuerTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** Sanctum stores the requested device name and abilities for the user. */
    public function test_it_issues_a_token_for_the_requested_user(): void
    {
        $user = UserModel::factory()->owner()->create();

        $issuer = new SanctumTokenIssuer;

        $plainTextToken = $issuer->issue(
            new UserId($user->id),
            'Chrome Windows',
            ['cocina', 'administrar'],
        );

        $this->assertNotSame('', $plainTextToken);

        $token = $user->tokens()->first();

        $this->assertNotNull($token);
        $this->assertSame('Chrome Windows', $token->name);
        $this->assertSame(['cocina', 'administrar'], $token->abilities);
    }
}
