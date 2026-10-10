<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class AuthenticatedSodaContext implements SodaContext
{
    /** Resolve the soda of the authenticated staff member; a person without soda is forbidden. */
    public function current(): SodaId
    {
        $user = Auth::user() ?? throw new AuthenticationException;

        return new SodaId($user->soda_id ?? throw new AuthorizationException);
    }
}
