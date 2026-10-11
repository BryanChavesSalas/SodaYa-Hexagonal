<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use Illuminate\Database\Eloquent\Model;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;

trait CreatesRowsOfASoda
{
    /** Scope the open transaction to the soda of each row before inserting it, as its row-level security policy demands. */
    public function configure(): static
    {
        return $this->afterMaking(function (Model $model): void {
            app(SodaTransaction::class)->scope((string) $model->getAttribute('soda_id'));
        });
    }
}
