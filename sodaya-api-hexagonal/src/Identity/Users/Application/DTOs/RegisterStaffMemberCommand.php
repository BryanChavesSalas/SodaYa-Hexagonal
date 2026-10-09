<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\DTOs;

use SensitiveParameter;
use Src\Identity\Users\Domain\ValueObjects\Role;

final readonly class RegisterStaffMemberCommand
{
    /** Carry the data needed to register a member of the staff of a soda. */
    public function __construct(
        public string $name,
        public string $email,
        #[SensitiveParameter] public string $password,
        public string $sodaId,
        public Role $role,
    ) {}
}
