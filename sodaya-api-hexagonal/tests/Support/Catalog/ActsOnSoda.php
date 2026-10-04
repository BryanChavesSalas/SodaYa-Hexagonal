<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

trait ActsOnSoda
{
    private SodaModel $soda;

    /** Create a soda and make it the current tenant. */
    protected function actOnNewSoda(): void
    {
        $this->soda = SodaModel::factory()->create();

        config(['sodaya.default_soda_id' => $this->soda->id]);
    }
}
