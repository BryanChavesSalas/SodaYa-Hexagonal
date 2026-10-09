<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Application\Contracts;

use SensitiveParameter;
use Src\Shared\Domain\ValueObjects\SodaId;

interface OwnerAccounts
{
    /** Register the account of the owner of a soda. */
    public function registerOwner(
        SodaId $sodaId,
        string $name,
        string $email,
        #[SensitiveParameter] string $password,
    ): void;
}
