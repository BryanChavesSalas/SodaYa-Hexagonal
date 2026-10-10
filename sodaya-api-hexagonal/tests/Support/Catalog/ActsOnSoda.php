<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

trait ActsOnSoda
{
    private SodaModel $soda;

    /** Create a soda and authenticate a member of its staff, the owner by default. */
    protected function actOnNewSoda(Role $role = Role::Owner): void
    {
        $this->soda = SodaModel::factory()->create();

        $this->actAsStaffOf($this->soda, $role);
    }

    /** Authenticate a new member of the staff of a soda with the abilities of the role. */
    protected function actAsStaffOf(SodaModel $soda, Role $role = Role::Owner): UserModel
    {
        $user = UserModel::factory()->create(['role' => $role->value, 'soda_id' => $soda->id]);

        return Sanctum::actingAs($user, $role->abilities());
    }
}
