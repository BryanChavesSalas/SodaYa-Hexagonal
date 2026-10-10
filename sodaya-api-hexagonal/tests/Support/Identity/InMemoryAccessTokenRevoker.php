<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Src\Identity\Users\Domain\Contracts\AccessTokenRevoker;
use Src\Identity\Users\Domain\ValueObjects\UserId;

final class InMemoryAccessTokenRevoker implements AccessTokenRevoker
{
    /** @var array<string, list<string>> */
    private array $tokens = [];

    /** Give the user an open token. */
    public function open(UserId $userId, string $tokenId): void
    {
        $this->tokens[$userId->value][] = $tokenId;
    }

    /**
     * List the tokens that are still open for the user.
     *
     * @return list<string>
     */
    public function openTokensOf(UserId $userId): array
    {
        return $this->tokens[$userId->value] ?? [];
    }

    /** Remove one token of the user. */
    public function revoke(UserId $userId, string $tokenId): void
    {
        $this->tokens[$userId->value] = array_values(array_filter(
            $this->tokens[$userId->value] ?? [],
            fn (string $open): bool => $open !== $tokenId,
        ));
    }

    /** Remove every token of the user. */
    public function revokeAll(UserId $userId): void
    {
        $this->tokens[$userId->value] = [];
    }
}
