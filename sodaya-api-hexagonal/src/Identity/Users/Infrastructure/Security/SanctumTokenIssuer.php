<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Security;

use Src\Identity\Users\Domain\Contracts\TokenIssuer;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

final readonly class SanctumTokenIssuer implements TokenIssuer
{
    /**
     * Create a Sanctum token using the requested device name and abilities.
     *
     * @param  list<string>  $abilities
     */
    public function issue(
        UserId $userId,
        string $deviceName,
        array $abilities,
    ): string {
        $model = UserModel::query()->findOrFail($userId->value);

        return $model->createToken($deviceName, $abilities)->plainTextToken;
    }
}
