<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Contracts;

use Src\Identity\Users\Domain\ValueObjects\UserId;

interface TokenIssuer
{
    /**
     * @param  list<string>  $abilities
     */
    public function issue(
        UserId $userId,
        string $deviceName,
        array $abilities,
    ): string;
}
