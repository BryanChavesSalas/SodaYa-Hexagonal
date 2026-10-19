<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use DateTimeImmutable;
use DateTimeZone;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Catalog\Promotions\Infrastructure\Persistence\Queries\EloquentActivePromotion;
use Src\Ordering\Orders\Infrastructure\Catalog\EloquentOrderableDishes;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class EloquentOrderableDishesTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    /** The requested dishes of the soda come back with their current name and price, active or not; a dish of another soda is missing. */
    public function test_reads_the_requested_dishes_of_the_soda_only(): void
    {
        $soda = SodaModel::factory()->create();
        $casado = DishModel::factory()->create(['soda_id' => $soda->id, 'name' => 'Casado de pollo', 'price' => 2_800]);
        $retired = DishModel::factory()->inactive()->create(['soda_id' => $soda->id]);
        DishModel::factory()->create(['soda_id' => $soda->id]);
        $foreign = DishModel::factory()->create(['name' => 'Casado de pollo']);
        $this->scopeQueriesTo($soda->id);

        $dishes = $this->catalog()->ofSoda(new SodaId($soda->id), [$casado->id, $retired->id, $foreign->id], $this->wednesdayAt('12:00'));

        $this->assertEqualsCanonicalizing([$casado->id, $retired->id], array_keys($dishes));
        $this->assertSame('Casado de pollo', $dishes[$casado->id]->name);
        $this->assertSame(2_800, $dishes[$casado->id]->unitPrice);
        $this->assertTrue($dishes[$casado->id]->active);
        $this->assertFalse($dishes[$retired->id]->active);
    }

    /** Inside the happy hour the price comes already discounted, in whole colones; at its end minute it is the base price again. */
    public function test_reads_the_price_with_the_happy_hour_of_the_moment(): void
    {
        $soda = SodaModel::factory()->create();
        $casado = DishModel::factory()->create(['soda_id' => $soda->id, 'price' => 2_800]);
        PromotionModel::factory()->create(['soda_id' => $soda->id, 'weekday' => 3, 'starts_at' => '14:00', 'ends_at' => '15:00', 'percentage' => 20]);
        $this->scopeQueriesTo($soda->id);

        $this->assertSame(2_240, $this->catalog()->ofSoda(new SodaId($soda->id), [$casado->id], $this->wednesdayAt('14:30'))[$casado->id]->unitPrice);
        $this->assertSame(2_800, $this->catalog()->ofSoda(new SodaId($soda->id), [$casado->id], $this->wednesdayAt('15:00'))[$casado->id]->unitPrice);
    }

    /** Without identifiers nothing is read. */
    public function test_reads_nothing_without_identifiers(): void
    {
        $soda = SodaModel::factory()->create();
        DishModel::factory()->create(['soda_id' => $soda->id]);
        $this->scopeQueriesTo($soda->id);

        $this->assertSame([], $this->catalog()->ofSoda(new SodaId($soda->id), [], $this->wednesdayAt('12:00')));
    }

    /** The adapter with a fresh reader of the happy hour. */
    private function catalog(): EloquentOrderableDishes
    {
        return new EloquentOrderableDishes(new EloquentActivePromotion);
    }

    /** Wednesday 21/10/2026 at that time of Costa Rica. */
    private function wednesdayAt(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("2026-10-21 {$time}:00", new DateTimeZone('America/Costa_Rica'));
    }
}
