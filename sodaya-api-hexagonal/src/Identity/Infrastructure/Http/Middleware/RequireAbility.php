<?php

declare(strict_types=1);

namespace Src\Identity\Infrastructure\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Src\Identity\Domain\Enums\Ability;

final readonly class RequireAbility
{
    /**
     * Build the middleware string that demands every given ability on the token.
     * A missing ability answers 403; a missing or invalid token answers 401.
     */
    public static function using(Ability ...$abilities): string
    {
        return CheckAbilities::class.':'.implode(',', array_map(
            static fn (Ability $ability): string => $ability->value,
            $abilities,
        ));
    }
}
