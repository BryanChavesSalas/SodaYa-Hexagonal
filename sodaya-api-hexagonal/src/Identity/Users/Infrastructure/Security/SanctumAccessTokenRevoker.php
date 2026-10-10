<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Security;

use Src\Identity\Users\Domain\Contracts\AccessTokenRevoker;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

final readonly class SanctumAccessTokenRevoker implements AccessTokenRevoker
{
    /** Delete one Sanctum token, scoped to its owner so nobody can revoke another account's token. */
    public function revoke(UserId $userId, string $tokenId): void
    {
        UserModel::query()
            ->findOrFail($userId->value)
            ->tokens()
            ->whereKey($tokenId)
            ->delete();
    }

    /** Delete every Sanctum token of the user. */
    public function revokeAll(UserId $userId): void
    {
        UserModel::query()
            ->findOrFail($userId->value)
            ->tokens()
            ->delete();
    }
}
