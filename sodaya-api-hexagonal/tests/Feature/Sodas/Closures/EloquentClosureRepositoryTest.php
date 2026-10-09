<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Closures;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Closures\Domain\Entities\Closure;
use Src\Sodas\Closures\Domain\Exceptions\ClosureAlreadyExistsException;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureId;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\Closures\Infrastructure\Persistence\Repositories\EloquentClosureRepository;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class EloquentClosureRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string TODAY = '2026-10-07';

    private EloquentClosureRepository $repository;

    private SodaId $sodaId;

    /** Create the repository and the soda every scenario works on. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentClosureRepository;
        $this->sodaId = new SodaId(SodaModel::factory()->create()->id);
    }

    /** A saved closure is read back with the same state. */
    public function test_saved_closure_is_read_back_unchanged(): void
    {
        $closure = $this->closure('2026-12-25', 'Navidad');

        $this->repository->save($closure);

        $this->assertEquals($closure, $this->repository->find($closure->id, $this->sodaId));
    }

    /** A closure without a reason keeps the reason empty. */
    public function test_closure_without_reason_is_read_back(): void
    {
        $closure = $this->closure('2026-12-25');

        $this->repository->save($closure);

        $this->assertNull($this->repository->find($closure->id, $this->sodaId)?->reason);
    }

    /** A closure is invisible when searched from another soda. */
    public function test_closure_of_another_soda_is_not_found(): void
    {
        $closure = $this->closure('2026-12-25');
        $this->repository->save($closure);

        $otherSodaId = new SodaId(SodaModel::factory()->create()->id);

        $this->assertNull($this->repository->find($closure->id, $otherSodaId));
        $this->assertNull($this->repository->onDate($otherSodaId, $closure->date));
    }

    /** The closure of a date is found by its date. */
    public function test_closure_is_found_by_date(): void
    {
        $closure = $this->closure('2026-12-25');
        $this->repository->save($closure);

        $this->assertEquals($closure, $this->repository->onDate($this->sodaId, new ClosureDate('2026-12-25')));
        $this->assertNull($this->repository->onDate($this->sodaId, new ClosureDate('2026-12-26')));
    }

    /** The listing starts on the given date, is ordered and is limited to the soda. */
    public function test_listing_starts_on_the_date_and_is_sorted(): void
    {
        $this->repository->save($this->closure('2026-12-25'));
        $this->repository->save($this->closure(self::TODAY));
        $this->repository->save($this->closure('2026-11-02'));
        ClosureModel::factory()->create(['soda_id' => $this->sodaId->value, 'closed_on' => '2026-10-01']);
        ClosureModel::factory()->create(['closed_on' => '2026-10-20']);

        $dates = array_map(
            fn (Closure $closure): string => $closure->date->value,
            $this->repository->from($this->sodaId, new ClosureDate(self::TODAY)),
        );

        $this->assertSame([self::TODAY, '2026-11-02', '2026-12-25'], $dates);
    }

    /** A closure whose date already passed is still readable. */
    public function test_past_closure_is_read_back(): void
    {
        $model = ClosureModel::factory()->create(['soda_id' => $this->sodaId->value, 'closed_on' => '2020-01-01']);

        $this->assertSame('2020-01-01', $this->repository->onDate($this->sodaId, new ClosureDate('2020-01-01'))?->date->value);
        $this->assertNotNull($this->repository->find(new ClosureId($model->id), $this->sodaId));
    }

    /** A repeated date raises a domain error and keeps the transaction usable. */
    public function test_repeated_date_raises_a_domain_error(): void
    {
        $this->repository->save($this->closure('2026-12-25'));

        try {
            $this->repository->save($this->closure('2026-12-25'));
            $this->fail('A repeated closure date was accepted.');
        } catch (ClosureAlreadyExistsException) {
            $this->assertDatabaseCount('closures', 1);
        }
    }

    /** A deleted closure is gone. */
    public function test_closure_is_deleted(): void
    {
        $closure = $this->closure('2026-12-25');
        $this->repository->save($closure);

        $this->repository->delete($closure);

        $this->assertDatabaseCount('closures', 0);
    }

    /** Build a closure of the scenario soda with a fresh identity. */
    private function closure(string $date, ?string $reason = null): Closure
    {
        return Closure::create(
            $this->repository->nextId(),
            $this->sodaId,
            new ClosureDate($date),
            $reason === null ? null : new ClosureReason($reason),
            new ClosureDate(self::TODAY),
        );
    }
}
