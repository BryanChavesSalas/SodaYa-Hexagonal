<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Promotions;

use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CurrentPriceWithPromotionTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private DishModel $casado;

    /** A soda open on Wednesdays with a casado at ₡2800 and 20 % off from 14:00 to 15:00; the cache starts empty. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->soda = SodaModel::factory()->create();
        TimeSlotModel::factory()->create(['soda_id' => $this->soda->id, 'day_of_week' => 3, 'opens_at' => '06:00', 'closes_at' => '20:00']);
        $this->casado = DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Casado de pollo', 'price' => 2_800, 'available_portions' => 10]);
        PromotionModel::factory()->create(['soda_id' => $this->soda->id, 'weekday' => 3, 'starts_at' => '14:00', 'ends_at' => '15:00', 'percentage' => 20]);

        Cache::flush();
    }

    /** The menu keeps the base price and adds the current one: discounted inside the window, equal outside it. */
    public function test_the_menu_shows_the_current_price_only_inside_the_window(): void
    {
        $this->onWednesdayAt('13:59');
        $this->menu()
            ->assertJsonPath('data.categorias.0.platos.0.precio', 2_800)
            ->assertJsonPath('data.categorias.0.platos.0.precio_vigente', 2_800);

        $this->onWednesdayAt('14:00');
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio_vigente', 2_240);

        $this->onWednesdayAt('14:30');
        $this->menu()
            ->assertJsonPath('data.categorias.0.platos.0.precio', 2_800)
            ->assertJsonPath('data.categorias.0.platos.0.precio_vigente', 2_240);

        $this->onWednesdayAt('15:00');
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio_vigente', 2_800);
    }

    /** The detail of a dish shows the same current price as the menu. */
    public function test_the_detail_of_a_dish_shows_the_current_price(): void
    {
        $this->onWednesdayAt('14:30');

        $this->getJson("/api/v1/sodas/{$this->soda->id}/platos/{$this->casado->id}")
            ->assertOk()
            ->assertJsonPath('data.precio', 2_800)
            ->assertJsonPath('data.precio_vigente', 2_240);
    }

    /** The current price is never cached: the hour passes, the dishes are not queried again, and the price goes back up. */
    public function test_the_current_price_is_decided_on_every_response_not_in_the_cache(): void
    {
        $this->onWednesdayAt('14:59');
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio_vigente', 2_240);

        $rows = Cache::get("menu:{$this->soda->id}:v1");
        $this->assertIsArray($rows);
        $this->assertArrayNotHasKey('current_price', $rows['categories'][0]['dishes'][0]);

        $this->onWednesdayAt('15:00');
        DB::enableQueryLog();
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio_vigente', 2_800);

        $dishQueries = array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], '"dishes"') && ! str_contains($query['query'], 'available_portions'),
        );
        $this->assertCount(0, $dishQueries);
    }

    /** Defining or deleting a happy hour changes the menu at once, although it was cached. */
    public function test_defining_a_promotion_changes_the_menu_at_once(): void
    {
        $this->onWednesdayAt('16:30');
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio_vigente', 2_800);

        $this->actAsStaffOf($this->soda);
        $promotionId = $this->postJson('/api/v1/cocina/promociones', ['dia' => 3, 'inicio' => '16:00', 'fin' => '17:00', 'porcentaje' => 15])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(2, Cache::get("menu:{$this->soda->id}:version"));
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio_vigente', 2_380);

        $this->deleteJson("/api/v1/cocina/promociones/{$promotionId}")->assertNoContent();
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio_vigente', 2_800);
    }

    /** Wednesday 21/10/2026 at that time of Costa Rica. */
    private function onWednesdayAt(string $time): void
    {
        $this->travelTo(new DateTimeImmutable("2026-10-21 {$time}:00-06:00"));
    }

    /** Read the public menu of the soda. */
    private function menu(): TestResponse
    {
        return $this->getJson("/api/v1/sodas/{$this->soda->id}/platos")->assertOk();
    }
}
