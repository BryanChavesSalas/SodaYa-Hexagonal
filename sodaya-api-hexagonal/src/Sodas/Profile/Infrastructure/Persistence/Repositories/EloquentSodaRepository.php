<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Domain\Contracts\SodaRepository;
use Src\Sodas\Profile\Domain\Entities\Soda;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

final readonly class EloquentSodaRepository implements SodaRepository
{
    /** Generate a time-ordered UUID for a new soda. */
    public function nextId(): SodaId
    {
        return new SodaId((string) Str::uuid7());
    }

    /** Insert or update the soda inside a savepoint. */
    public function save(Soda $soda): void
    {
        DB::transaction(fn () => SodaModel::query()->updateOrCreate(
            ['id' => $soda->id->value],
            [
                'name' => $soda->name->value,
                'payment_account_id' => $soda->paymentAccountId?->value,
            ],
        ));
    }
}
