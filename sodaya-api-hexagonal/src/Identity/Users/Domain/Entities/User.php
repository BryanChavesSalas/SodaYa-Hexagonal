<?php

declare(strict_types=1);

namespace Src\Identity\Users\Domain\Entities;

use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class User
{
    /** Hold the full state of a user account. */
    private function __construct(
        public UserId $id,
        public UserName $name,
        public Email $email,
        public string $passwordHash,
        public Role $role,
        public ?SodaId $sodaId,
        public bool $active,
    ) {}

    /** Register an active account; staff always belongs to a soda and customers never do. */
    public static function create(
        UserId $id,
        UserName $name,
        Email $email,
        string $passwordHash,
        Role $role,
        ?SodaId $sodaId,
    ): self {
        if ($role->isStaff() && $sodaId === null) {
            throw new InvalidValueException('identity.staff_without_soda');
        }

        if (! $role->isStaff() && $sodaId !== null) {
            throw new InvalidValueException('identity.customer_with_soda');
        }

        return new self($id, $name, $email, $passwordHash, $role, $sodaId, true);
    }

    /** Rebuild an account from its stored state. */
    public static function reconstitute(
        UserId $id,
        UserName $name,
        Email $email,
        string $passwordHash,
        Role $role,
        ?SodaId $sodaId,
        bool $active,
    ): self {
        return new self($id, $name, $email, $passwordHash, $role, $sodaId, $active);
    }
}
