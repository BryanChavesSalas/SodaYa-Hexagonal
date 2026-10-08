<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Contracts;

use Src\Identity\Users\Domain\Entities\User;

interface AccessTokenIssuer
{
    /**
     * Create an access token for the authenticated user.
     *
     * @param  list<string>  $abilities
     */
    public function issue(User $user, string $deviceName, array $abilities): string;
}
