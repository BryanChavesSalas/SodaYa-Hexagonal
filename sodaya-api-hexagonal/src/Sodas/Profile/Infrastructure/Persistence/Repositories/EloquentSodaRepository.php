<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Domain\Contracts\SodaRepository;
use Src\Sodas\Profile\Domain\Entities\Soda;
use Src\Sodas\Profile\Domain\ValueObjects\PaymentAccountId;
use Src\Sodas\Profile\Domain\ValueObjects\SodaName;
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
                'logo_path' => $soda->logoPath,
            ],
        ));
    }

    /** Find the soda and rebuild it from its stored state. */
    public function find(SodaId $id): ?Soda
    {
        $model = SodaModel::query()->find($id->value);

        return $model === null ? null : Soda::reconstitute(
            new SodaId($model->id),
            new SodaName($model->name),
            $model->payment_account_id === null ? null : new PaymentAccountId($model->payment_account_id),
            $model->logo_path,
        );
    }
}
