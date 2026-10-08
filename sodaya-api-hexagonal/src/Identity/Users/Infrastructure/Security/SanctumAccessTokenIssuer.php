<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Security;

use Src\Identity\Users\Domain\Contracts\AccessTokenIssuer;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

final readonly class SanctumAccessTokenIssuer implements AccessTokenIssuer
{
    /**
     * Create a Sanctum token using the requested device name and role abilities.
     *
     * @param  list<string>  $abilities
     */
    public function issue(User $user, string $deviceName, array $abilities): string
    {
        $model = UserModel::query()->findOrFail($user->id->value);

        return $model->createToken($deviceName, $abilities)->plainTextToken;
    }
}
