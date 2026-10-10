<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Contracts;

use Src\Identity\Users\Domain\ValueObjects\UserId;

interface AccessTokenRevoker
{
    /** Revoke one access token, but only when it belongs to the user. */
    public function revoke(UserId $userId, string $tokenId): void;

    /** Revoke every access token of the user. */
    public function revokeAll(UserId $userId): void;
}
