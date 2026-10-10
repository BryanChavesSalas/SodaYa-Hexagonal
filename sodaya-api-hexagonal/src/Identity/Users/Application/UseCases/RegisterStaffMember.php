<?php

declare(strict_types=1);

namespace Src\Identity\Users\Application\UseCases;

use Src\Identity\Users\Application\DTOs\RegisterStaffMemberCommand;
use Src\Identity\Users\Domain\Contracts\PasswordHasher;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\PlainPassword;
use Src\Identity\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class RegisterStaffMember
{
    /** Receive the user repository and password hasher ports. */
    public function __construct(
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
    ) {}

    /**
     * Register an active account for a member of the staff of a soda.
     *
     * @throws EmailAlreadyRegisteredException
     */
    public function execute(RegisterStaffMemberCommand $command): User
    {
        $name = new UserName($command->name);
        $email = new Email($command->email);
        $password = new PlainPassword($command->password);
        $sodaId = new SodaId($command->sodaId);

        $user = User::create(
            $this->users->nextId(),
            $name,
            $email,
            $this->passwordHasher->hash($password->value),
            $command->role,
            $sodaId,
        );

        $this->users->save($user);

        return $user;
    }
}
