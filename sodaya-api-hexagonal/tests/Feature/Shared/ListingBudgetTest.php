<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ListingBudgetTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const int MAX_PAGE_BYTES = 100 * 1024;

    private int $created = 0;

    /** Authenticate the owner of a soda on a Monday. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 09:00:00');
        $this->actOnNewSoda();
    }

    /** A listing runs the same queries for 1 row as for 50: nothing is read row by row. */
    #[DataProvider('listings')]
    public function test_listing_runs_the_same_queries_for_one_row_or_fifty(string $kind, string $endpoint): void
    {
        $this->rows($kind, 1);
        $withOne = $this->queriesOf($endpoint);

        $this->rows($kind, 49);
        $withFifty = $this->queriesOf($endpoint);

        $this->assertSame($withOne, $withFifty);
    }

    /** A full page of 50 rows with every text at its maximum length weighs 100 KB or less. */
    #[DataProvider('listings')]
    public function test_full_page_weighs_at_most_100_kb(string $kind, string $endpoint): void
    {
        $this->rows($kind, 50);

        $response = $this->getJson($this->address($endpoint))->assertOk();

        $this->assertLessThanOrEqual(self::MAX_PAGE_BYTES, strlen((string) $response->getContent()));
    }

    /**
     * Listings, staff and public, and the kind of row they show.
     *
     * @return array<string, array{string, string}>
     */
    public static function listings(): array
    {
        return [
            'dishes' => ['dish', '/api/v1/cocina/platos?por_pagina=50'],
            'categories' => ['category', '/api/v1/cocina/categorias?por_pagina=50'],
            'opening hours' => ['slot', '/api/v1/cocina/horario?por_pagina=50'],
            'closures' => ['closure', '/api/v1/cocina/cierres?por_pagina=50'],
            'kitchen orders' => ['order', '/api/v1/cocina/pedidos?por_pagina=50'],
            'public menu' => ['menu dish', '/api/v1/sodas/{soda}/platos'],
        ];
    }

    /** Number of queries a request runs. */
    private function queriesOf(string $endpoint): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($this->address($endpoint))->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    /** Put the soda of the test into the address. */
    private function address(string $endpoint): string
    {
        return str_replace('{soda}', $this->soda->id, $endpoint);
    }

    /** Create rows of a kind with their texts at the maximum length; menu dishes each get their own category and orders one line each. */
    private function rows(string $kind, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $n = ++$this->created;
            $name = fn (int $length): string => mb_str_pad(sprintf('%02d ', $n), $length, 'ñ');

            match ($kind) {
                'dish' => DishModel::factory()->create([
                    'soda_id' => $this->soda->id,
                    'name' => $name(120),
                    'description' => str_repeat('á', 500),
                    'photo_path' => 'platos/0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99.webp',
                ]),
                'menu dish' => DishModel::factory()->create([
                    'soda_id' => $this->soda->id,
                    'category_id' => CategoryModel::factory()->create(['soda_id' => $this->soda->id, 'name' => $name(60)])->id,
                    'name' => $name(120),
                    'description' => str_repeat('á', 500),
                ]),
                'category' => CategoryModel::factory()->create(['soda_id' => $this->soda->id, 'name' => $name(60)]),
                'slot' => TimeSlotModel::factory()->create([
                    'soda_id' => $this->soda->id,
                    'day_of_week' => ($n - 1) % 7 + 1,
                    'opens_at' => sprintf('%02d:%02d', intdiv($n - 1, 7), 0),
                    'closes_at' => sprintf('%02d:%02d', intdiv($n - 1, 7), 30),
                ]),
                'closure' => ClosureModel::factory()->create([
                    'soda_id' => $this->soda->id,
                    'closed_on' => now()->addDays($n)->toDateString(),
                    'reason' => str_repeat('é', 200),
                ]),
                'order' => OrderLineModel::factory()
                    ->for(OrderModel::factory()->confirmed()->create(['soda_id' => $this->soda->id]), 'order')
                    ->create(['dish_name' => $name(120), 'quantity' => 20]),
            };
        }
    }
}
