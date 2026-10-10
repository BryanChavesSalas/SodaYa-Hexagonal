<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Src\Identity\Users\Domain\Contracts\TokenIssuer;
use Src\Identity\Users\Domain\ValueObjects\UserId;

final class FakeTokenIssuer implements TokenIssuer
{
    /** @var list<array{string, string, list<string>}> */
    public private(set) array $issued = [];

    /**
     * Remember the token request and answer a predictable token.
     *
     * @param  list<string>  $abilities
     */
    public function issue(UserId $userId, string $deviceName, array $abilities): string
    {
        $this->issued[] = [$userId->value, $deviceName, $abilities];

        return count($this->issued).'|token-de-prueba';
    }
}
