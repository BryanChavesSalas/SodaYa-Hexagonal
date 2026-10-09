<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

trait ActsOnSoda
{
    private SodaModel $soda;

    /** Create a soda and authenticate as its owner. */
    protected function actOnNewSoda(): void
    {
        $this->soda = SodaModel::factory()->create();

        Sanctum::actingAs(UserModel::factory()->owner()->create(['soda_id' => $this->soda->id]));
    }
}
