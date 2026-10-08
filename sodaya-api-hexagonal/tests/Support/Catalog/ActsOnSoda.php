<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Domain\Enums\Role;
use Src\Identity\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

trait ActsOnSoda
{
    private SodaModel $soda;

    /** Create a soda, make it the current tenant and sign in as its owner. */
    protected function actOnNewSoda(): void
    {
        $this->soda = SodaModel::factory()->create();

        config(['sodaya.default_soda_id' => $this->soda->id]);

        Sanctum::actingAs(UserModel::factory()->owner($this->soda->id)->create(), Role::Owner->tokenAbilities());
    }
}
