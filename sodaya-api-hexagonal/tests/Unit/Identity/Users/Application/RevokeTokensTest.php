<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users\Application;

use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Application\UseCases\RevokeAllTokens;
use Src\Identity\Users\Application\UseCases\RevokeCurrentToken;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Tests\Support\Identity\InMemoryAccessTokenRevoker;

final class RevokeTokensTest extends TestCase
{
    private const string USER_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    private const string OTHER_USER_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d33';

    private InMemoryAccessTokenRevoker $tokens;

    /** Give the user two open sessions and another user one. */
    protected function setUp(): void
    {
        $this->tokens = new InMemoryAccessTokenRevoker;
        $this->tokens->open(new UserId(self::USER_ID), '1');
        $this->tokens->open(new UserId(self::USER_ID), '2');
        $this->tokens->open(new UserId(self::OTHER_USER_ID), '3');
    }

    /** Revoking the current token leaves the other sessions open. */
    public function test_revokes_only_the_current_token(): void
    {
        (new RevokeCurrentToken($this->tokens))->execute(self::USER_ID, '1');

        $this->assertSame(['2'], $this->tokens->openTokensOf(new UserId(self::USER_ID)));
    }

    /** Revoking all tokens closes every session of the user and no one else's. */
    public function test_revokes_every_token_of_the_user(): void
    {
        (new RevokeAllTokens($this->tokens))->execute(self::USER_ID);

        $this->assertSame([], $this->tokens->openTokensOf(new UserId(self::USER_ID)));
        $this->assertSame(['3'], $this->tokens->openTokensOf(new UserId(self::OTHER_USER_ID)));
    }
}
