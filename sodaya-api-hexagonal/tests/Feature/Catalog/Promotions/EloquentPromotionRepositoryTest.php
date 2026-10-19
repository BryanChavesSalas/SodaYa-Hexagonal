<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Promotions;

use Src\Catalog\Promotions\Domain\Entities\Promotion;
use Src\Catalog\Promotions\Domain\Exceptions\PromotionOverlapsException;
use Src\Catalog\Promotions\Domain\ValueObjects\DiscountPercentage;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeOfDay;
use Src\Catalog\Promotions\Domain\ValueObjects\TimeWindow;
use Src\Catalog\Promotions\Domain\ValueObjects\Weekday;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Catalog\Promotions\Infrastructure\Persistence\Repositories\EloquentPromotionRepository;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class EloquentPromotionRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private EloquentPromotionRepository $repository;

    private SodaId $sodaId;

    /** Create the repository and the soda every scenario works on, and scope the queries to it. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentPromotionRepository;
        $this->sodaId = new SodaId(SodaModel::factory()->create()->id);
        $this->scopeQueriesTo($this->sodaId->value);
    }

    /** A saved happy hour is read back with the same state. */
    public function test_saved_promotion_is_read_back_unchanged(): void
    {
        $promotion = $this->promotion(3, '14:00', '15:00', 20);

        $this->repository->save($promotion);

        $this->assertEquals($promotion, $this->repository->find($promotion->id, $this->sodaId));
    }

    /** A happy hour is invisible when searched from another soda. */
    public function test_promotion_of_another_soda_is_not_found(): void
    {
        $promotion = $this->promotion(3, '14:00', '15:00', 20);
        $this->repository->save($promotion);

        $this->assertNull($this->repository->find($promotion->id, new SodaId(SodaModel::factory()->create()->id)));
    }

    /** The page follows the week and the clock and is limited to the soda. */
    public function test_page_is_ordered_by_day_and_start_time(): void
    {
        $this->repository->save($this->promotion(4, '14:00', '15:00', 10));
        $this->repository->save($this->promotion(3, '16:00', '17:00', 15));
        $this->repository->save($this->promotion(3, '14:00', '15:00', 20));
        PromotionModel::factory()->create();
        $this->scopeQueriesTo($this->sodaId->value);

        $page = $this->repository->pageOf($this->sodaId, new PageRequest);

        $this->assertSame(3, $page->total);
        $this->assertSame(
            ['3 14:00', '3 16:00', '4 14:00'],
            array_map(fn (Promotion $promotion): string => "{$promotion->weekday->value} {$promotion->window->startsAt->value}", $page->items),
        );
    }

    /** The exclusion constraint surfaces as the domain error and keeps the transaction usable. */
    public function test_overlapping_promotion_raises_the_domain_error(): void
    {
        $this->repository->save($this->promotion(3, '14:00', '15:00', 20));

        try {
            $this->repository->save($this->promotion(3, '14:30', '16:00', 10));
            $this->fail('An overlapping happy hour was accepted.');
        } catch (PromotionOverlapsException) {
            $this->assertDatabaseCount('promotions', 1);
        }
    }

    /** A deleted happy hour is gone. */
    public function test_deleted_promotion_is_removed(): void
    {
        $promotion = $this->promotion(3, '14:00', '15:00', 20);
        $this->repository->save($promotion);

        $this->repository->delete($promotion);

        $this->assertSame(0, $this->repository->pageOf($this->sodaId, new PageRequest)->total);
    }

    /** Build a happy hour of the scenario soda with a fresh identity. */
    private function promotion(int $weekday, string $startsAt, string $endsAt, int $percentage): Promotion
    {
        return Promotion::create(
            $this->repository->nextId(),
            $this->sodaId,
            new Weekday($weekday),
            new TimeWindow(new TimeOfDay($startsAt), new TimeOfDay($endsAt)),
            new DiscountPercentage($percentage),
        );
    }
}
