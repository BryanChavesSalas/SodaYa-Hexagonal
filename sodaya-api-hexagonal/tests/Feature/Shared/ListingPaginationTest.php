<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ListingPaginationTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    /** Give the owner of a soda 20 rows of each kind, on a Monday. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 09:00:00');
        $this->actOnNewSoda();

        for ($i = 0; $i < 20; $i++) {
            CategoryModel::factory()->create(['soda_id' => $this->soda->id, 'name' => sprintf('Categoría %02d', $i)]);
            DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => sprintf('Plato %02d', $i)]);
            ClosureModel::factory()->create(['soda_id' => $this->soda->id, 'closed_on' => now()->addDays($i + 1)->toDateString()]);
            OrderModel::factory()->create(['soda_id' => $this->soda->id, 'created_at' => now()->addMinutes($i)]);
            TimeSlotModel::factory()->create([
                'soda_id' => $this->soda->id,
                'day_of_week' => $i % 7 + 1,
                'opens_at' => sprintf('%02d:00', 6 + intdiv($i, 7) * 3),
                'closes_at' => sprintf('%02d:00', 8 + intdiv($i, 7) * 3),
            ]);
        }
    }

    /** Without parameters a listing answers the first 15 rows and says how many pages there are. */
    #[DataProvider('listings')]
    public function test_listing_answers_the_first_page_of_fifteen(string $endpoint): void
    {
        $this->getJson($endpoint)
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta', ['pagina' => 1, 'por_pagina' => 15, 'total' => 20, 'ultima_pagina' => 2]);
    }

    /** The second page continues where the first one ended, without repeating rows. */
    #[DataProvider('listings')]
    public function test_pages_do_not_repeat_rows(string $endpoint): void
    {
        $first = $this->getJson($endpoint.'?por_pagina=12')->assertOk()->json('data.*.id');
        $second = $this->getJson($endpoint.'?por_pagina=12&pagina=2')->assertOk()->assertJsonCount(8, 'data')->json('data.*.id');

        $this->assertSame([], array_intersect($first, $second));
    }

    /** A page after the last one is empty but keeps the total. */
    #[DataProvider('listings')]
    public function test_page_after_the_last_is_empty(string $endpoint): void
    {
        $this->getJson($endpoint.'?pagina=3')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 20);
    }

    /** At most 50 rows per page; a page starts at 1. */
    #[DataProvider('listings')]
    public function test_page_out_of_range_is_rejected(string $endpoint): void
    {
        $this->getJson($endpoint.'?por_pagina=51&pagina=0')
            ->assertUnprocessable()
            ->assertJsonPath('errores.por_pagina.0', 'El campo elementos por página debe estar entre 1 y 50.')
            ->assertJsonPath('errores.pagina.0', 'El campo página debe ser al menos 1.');
    }

    /**
     * Staff listings.
     *
     * @return array<string, array{string}>
     */
    public static function listings(): array
    {
        return [
            'dishes' => ['/api/v1/cocina/platos'],
            'categories' => ['/api/v1/cocina/categorias'],
            'opening hours' => ['/api/v1/cocina/horario'],
            'closures' => ['/api/v1/cocina/cierres'],
            'kitchen orders' => ['/api/v1/cocina/pedidos'],
        ];
    }

    /** The public menu is not paginated: a visitor gets every active dish at once. */
    public function test_public_menu_is_complete(): void
    {
        $this->getJson("/api/v1/sodas/{$this->soda->id}/platos")
            ->assertOk()
            ->assertJsonCount(20, 'data.categorias.0.platos');
    }
}
