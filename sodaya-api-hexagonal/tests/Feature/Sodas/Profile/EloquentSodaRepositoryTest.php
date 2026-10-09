<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Profile;

use Src\Sodas\Profile\Domain\Entities\Soda;
use Src\Sodas\Profile\Domain\ValueObjects\PaymentAccountId;
use Src\Sodas\Profile\Domain\ValueObjects\SodaName;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Repositories\EloquentSodaRepository;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class EloquentSodaRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private EloquentSodaRepository $repository;

    /** Create the repository every scenario works on. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentSodaRepository;
    }

    /** A saved soda keeps its name and its payment account. */
    public function test_soda_is_saved_with_its_payment_account(): void
    {
        $soda = Soda::create($this->repository->nextId(), new SodaName('Soda La Esquina'), new PaymentAccountId('acct_123'));

        $this->repository->save($soda);

        $model = SodaModel::query()->findOrFail($soda->id->value);
        $this->assertSame('Soda La Esquina', $model->name);
        $this->assertSame('acct_123', $model->payment_account_id);
    }

    /** A soda saved without a payment account keeps the column empty. */
    public function test_soda_without_a_payment_account_is_saved_as_null(): void
    {
        $soda = Soda::create($this->repository->nextId(), new SodaName('Soda La Esquina'), null);

        $this->repository->save($soda);

        $this->assertNull(SodaModel::query()->findOrFail($soda->id->value)->payment_account_id);
    }

    /** Saving a soda again updates its row instead of adding another. */
    public function test_saving_the_same_soda_again_updates_it(): void
    {
        $id = $this->repository->nextId();
        $this->repository->save(Soda::create($id, new SodaName('Soda La Esquina'), null));

        $this->repository->save(Soda::reconstitute($id, new SodaName('Soda El Parque'), new PaymentAccountId('acct_456')));

        $this->assertDatabaseCount('sodas', 1);
        $model = SodaModel::query()->findOrFail($id->value);
        $this->assertSame('Soda El Parque', $model->name);
        $this->assertSame('acct_456', $model->payment_account_id);
    }

    /** Every new identity is a distinct time-ordered UUID. */
    public function test_next_id_returns_distinct_uuid_v7_values(): void
    {
        $first = $this->repository->nextId()->value;
        $second = $this->repository->nextId()->value;

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $second);
        $this->assertNotSame($first, $second);
    }
}
