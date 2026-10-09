<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Persistence\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Identity\Users\Domain\Contracts\UserRepository;
use Src\Identity\Users\Domain\Entities\User;
use Src\Identity\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Identity\Users\Domain\ValueObjects\Email;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Domain\ValueObjects\UserName;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class EloquentUserRepository implements UserRepository
{
    /** Generate a time-ordered UUID for a new user. */
    public function nextId(): UserId
    {
        return new UserId((string) Str::uuid7());
    }

    /** Insert or update the user inside a savepoint and translate a repeated email. */
    public function save(User $user): void
    {
        try {
            DB::transaction(fn () => UserModel::query()->updateOrCreate(
                ['id' => $user->id->value],
                [
                    'name' => $user->name->value,
                    'email' => $user->email->value,
                    'password' => $user->passwordHash,
                    'role' => $user->role->value,
                    'soda_id' => $user->sodaId?->value,
                    'is_active' => $user->active,
                ],
            ));
        } catch (UniqueConstraintViolationException) {
            throw EmailAlreadyRegisteredException::create();
        }
    }

    /** Find the account by its normalised email. */
    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::query()->where('email', $email->value)->first();

        return $model === null ? null : $this->toDomain($model);
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(UserModel $model): User
    {
        return User::reconstitute(
            new UserId($model->id),
            new UserName($model->name),
            new Email($model->email),
            $model->password,
            Role::from($model->role),
            $model->soda_id === null ? null : new SodaId($model->soda_id),
            $model->is_active,
        );
    }
}
