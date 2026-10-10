<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure\Identity;

use SensitiveParameter;
use Src\Identity\Users\Application\DTOs\RegisterStaffMemberCommand;
use Src\Identity\Users\Application\UseCases\RegisterStaffMember;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Application\Contracts\OwnerAccounts;

final readonly class IdentityOwnerAccounts implements OwnerAccounts
{
    /** Receive the Identity use case that registers staff. */
    public function __construct(private RegisterStaffMember $registerStaffMember) {}

    /** Register the owner as a staff member of the soda through the Identity context. */
    public function registerOwner(
        SodaId $sodaId,
        string $name,
        string $email,
        #[SensitiveParameter] string $password,
    ): void {
        $this->registerStaffMember->execute(
            new RegisterStaffMemberCommand($name, $email, $password, $sodaId->value, Role::Owner),
        );
    }
}
