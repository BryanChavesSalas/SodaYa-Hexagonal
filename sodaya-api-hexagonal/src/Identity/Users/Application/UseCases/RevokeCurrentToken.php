<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\UseCases;

use Src\Identity\Users\Domain\Contracts\AccessTokenRevoker;
use Src\Identity\Users\Domain\ValueObjects\UserId;

final readonly class RevokeCurrentToken
{
    public function __construct(private AccessTokenRevoker $tokens) {}

    /** Close the session of the device that made the request. */
    public function execute(string $userId, string $tokenId): void
    {
        $this->tokens->revoke(new UserId($userId), $tokenId);
    }
}
