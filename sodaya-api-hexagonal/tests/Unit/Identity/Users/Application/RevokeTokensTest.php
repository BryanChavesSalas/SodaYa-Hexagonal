<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users\Application;

use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Application\UseCases\RevokeAllTokens;
use Src\Identity\Users\Application\UseCases\RevokeCurrentToken;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Tests\Support\Identity\FakeTokenRevoker;

final class RevokeTokensTest extends TestCase
{
    private const string USER_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d44';

    private FakeTokenRevoker $tokens;

    /** Start every scenario without revocations. */
    protected function setUp(): void
    {
        $this->tokens = new FakeTokenRevoker;
    }

    /** Closing the current session revokes only the token in use. */
    public function test_revokes_the_current_token_of_the_user(): void
    {
        new RevokeCurrentToken($this->tokens)->execute(self::USER_ID, '7');

        $this->assertSame([[self::USER_ID, '7']], $this->tokens->revoked);
        $this->assertSame([], $this->tokens->revokedAll);
    }

    /** Closing every session revokes all the tokens of the user. */
    public function test_revokes_every_token_of_the_user(): void
    {
        new RevokeAllTokens($this->tokens)->execute(self::USER_ID);

        $this->assertSame([self::USER_ID], $this->tokens->revokedAll);
        $this->assertSame([], $this->tokens->revoked);
    }

    /** A malformed user identity never reaches the port. */
    public function test_rejects_a_malformed_user_identity(): void
    {
        try {
            new RevokeAllTokens($this->tokens)->execute('7');
            $this->fail('A malformed user identity was accepted.');
        } catch (InvalidValueException) {
            $this->assertSame([], $this->tokens->revokedAll);
        }
    }
}
