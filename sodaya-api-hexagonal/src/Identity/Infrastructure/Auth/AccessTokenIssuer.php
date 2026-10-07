<?php

declare(strict_types=1);

namespace Src\Identity\Infrastructure\Auth;

use Laravel\Sanctum\NewAccessToken;
use Src\Identity\Infrastructure\Persistence\Models\UserModel;

final readonly class AccessTokenIssuer
{
    /** Issue an API token carrying exactly the abilities of the user's role. */
    public function issueFor(UserModel $user): NewAccessToken
    {
        return $user->createToken('api', $user->role->tokenAbilities());
    }
}
