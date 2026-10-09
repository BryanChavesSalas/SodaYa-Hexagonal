<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Src\Identity\Users\Domain\Contracts\TokenIssuer;
use Src\Identity\Users\Domain\ValueObjects\UserId;

final class FakeTokenIssuer implements TokenIssuer
{
    public ?UserId $userId = null;

    public ?string $deviceName = null;

    /** @var list<string> */
    public array $abilities = [];

    public function issue(
        UserId $userId,
        string $deviceName,
        array $abilities,
    ): string {
        $this->userId = $userId;
        $this->deviceName = $deviceName;
        $this->abilities = $abilities;

        return 'token-de-prueba';
    }
}
