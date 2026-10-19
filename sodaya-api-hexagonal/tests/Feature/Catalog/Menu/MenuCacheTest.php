<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Menu;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\TestImages;
use Tests\TestCase;

final class MenuCacheTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private CategoryModel $category;

    private DishModel $casado;

    private TimeSlotModel $tuesday;

    private ClosureModel $christmas;

    /**
     * A soda open on Monday and Tuesday, closed on Christmas, with one casado at ₡2800 and 5 portions,
     * consulted on Monday at noon. The factories already raised the version of the menu: the scenario
     * starts with an empty cache, as a soda nobody edited.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->soda = SodaModel::factory()->create(['name' => 'Soda La Esquina']);
        TimeSlotModel::factory()->create(['soda_id' => $this->soda->id, 'day_of_week' => 1, 'opens_at' => '06:00', 'closes_at' => '14:00']);
        $this->tuesday = TimeSlotModel::factory()->create(['soda_id' => $this->soda->id, 'day_of_week' => 2]);
        $this->christmas = ClosureModel::factory()->create(['soda_id' => $this->soda->id, 'closed_on' => '2026-12-25']);
        $this->category = CategoryModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Casados']);
        $this->casado = DishModel::factory()->create([
            'soda_id' => $this->soda->id,
            'category_id' => $this->category->id,
            'name' => 'Casado de pollo',
            'price' => 2_800,
            'available_portions' => 5,
        ]);

        Cache::flush();
    }

    /** The second reading of the menu comes from the cache: the dishes are not queried, only their portions. */
    public function test_the_second_reading_does_not_query_the_dishes(): void
    {
        $this->menu()->assertOk();

        DB::enableQueryLog();
        $this->menu()->assertOk()->assertJsonPath('data.categorias.0.platos.0.precio', 2_800);

        $dishQueries = array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_contains($query['query'], '"dishes"')
                && ! str_contains($query['query'], 'available_portions'),
        );

        $this->assertCount(0, $dishQueries);
    }

    /** The cache keeps the menu as plain arrays, under the soda and its version, without the portions. */
    public function test_the_cache_keeps_arrays_under_the_soda_and_its_version(): void
    {
        $this->menu()->assertOk();

        $this->assertNull(Cache::get("menu:{$this->soda->id}:version"));
        $rows = Cache::get("menu:{$this->soda->id}:v1");

        $this->assertIsArray($rows);
        $this->assertSame('Soda La Esquina', $rows['soda_name']);
        $this->assertSame(['id', 'name', 'description', 'price', 'preparation_minutes', 'updated_at', 'photo_path'], array_keys($rows['categories'][0]['dishes'][0]));
    }

    /** Editing a dish raises the version of its soda and the next reading shows the change. */
    public function test_editing_a_dish_invalidates_the_menu_of_its_soda(): void
    {
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio', 2_800);

        $this->actAsStaffOf($this->soda);
        $this->patchJson("/api/v1/cocina/platos/{$this->casado->id}", ['precio' => 3_000])->assertOk();

        $this->assertSame(2, Cache::get("menu:{$this->soda->id}:version"));
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio', 3_000);
        $this->assertIsArray(Cache::get("menu:{$this->soda->id}:v2"));
    }

    /** Renaming a category, and deleting it, change the menu at once. */
    public function test_changing_a_category_invalidates_the_menu(): void
    {
        $this->menu()->assertJsonPath('data.categorias.0.nombre', 'Casados');

        $this->actAsStaffOf($this->soda);
        $this->patchJson("/api/v1/cocina/categorias/{$this->category->id}", ['nombre' => 'Platos fuertes'])->assertOk();
        $this->menu()->assertJsonPath('data.categorias.0.nombre', 'Platos fuertes');

        $this->deleteJson("/api/v1/cocina/categorias/{$this->category->id}")->assertNoContent();
        $this->menu()->assertJsonPath('data.categorias.0.nombre', 'Otros');
    }

    /** Every edit of the schedule and of the closures, and every photo or logo, raises the version. */
    #[DataProvider('edits')]
    public function test_every_edit_of_the_owner_raises_the_version(string $edit): void
    {
        Storage::fake('public');
        $this->menu()->assertOk();
        $this->actAsStaffOf($this->soda);

        $response = match ($edit) {
            'add a slot' => $this->postJson('/api/v1/cocina/horario', ['dia' => 3, 'abre' => '08:00', 'cierra' => '12:00']),
            'delete a slot' => $this->deleteJson("/api/v1/cocina/horario/{$this->tuesday->id}"),
            'register a closure' => $this->postJson('/api/v1/cocina/cierres', ['fecha' => '2026-12-24']),
            'delete a closure' => $this->deleteJson("/api/v1/cocina/cierres/{$this->christmas->id}"),
            'change a photo' => $this->post("/api/v1/cocina/platos/{$this->casado->id}/foto", ['foto' => TestImages::png()], ['Accept' => 'application/json']),
            default => $this->post('/api/v1/cocina/logo', ['logo' => TestImages::png()], ['Accept' => 'application/json']),
        };

        $response->assertSuccessful();
        $this->assertSame(2, Cache::get("menu:{$this->soda->id}:version"));
    }

    /**
     * Edits that do not touch the price or the name of a dish but still change what the visitor sees.
     *
     * @return array<string, array{string}>
     */
    public static function edits(): array
    {
        return [
            'add a slot' => ['add a slot'],
            'delete a slot' => ['delete a slot'],
            'register a closure' => ['register a closure'],
            'delete a closure' => ['delete a closure'],
            'change a photo' => ['change a photo'],
            'change the logo' => ['change the logo'],
        ];
    }

    /** An order takes portions without editing the menu: the cached menu still shows the portions that remain. */
    public function test_portions_stay_live_while_the_menu_is_cached(): void
    {
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.porciones_disponibles', 5);

        $this->placeOrderFor($this->casado, 2)->assertCreated();

        $this->assertNull(Cache::get("menu:{$this->soda->id}:version"));
        $this->menu()
            ->assertJsonPath('data.categorias.0.platos.0.porciones_disponibles', 3)
            ->assertJsonPath('data.categorias.0.platos.0.agotado', false);
    }

    /** Each soda has its own keys: reading one menu never serves the dishes of another. */
    public function test_two_sodas_do_not_share_the_cached_menu(): void
    {
        $other = SodaModel::factory()->create();
        $olla = DishModel::factory()->create(['soda_id' => $other->id, 'name' => 'Olla de carne']);
        Cache::flush();

        $this->menu()->assertJsonPath('data.categorias.0.platos.*.id', [$this->casado->id]);
        $this->menu($other)->assertJsonPath('data.categorias.0.platos.*.id', [$olla->id]);
        $this->menu()->assertJsonPath('data.categorias.0.platos.*.id', [$this->casado->id]);

        $this->assertIsArray(Cache::get("menu:{$this->soda->id}:v1"));
        $this->assertIsArray(Cache::get("menu:{$other->id}:v1"));
    }

    /** A change the observers never see, like one made with SQL, is served once the menu expires. */
    public function test_a_change_the_observers_miss_is_served_after_the_ttl(): void
    {
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio', 2_800);

        DB::table('dishes')->where('id', $this->casado->id)->update(['price' => 3_000]);

        $this->travel(539)->seconds();
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio', 2_800);

        $this->travel(62)->seconds();
        $this->menu()->assertJsonPath('data.categorias.0.platos.0.precio', 3_000);
    }

    /** Read the public menu, by default of the scenario soda. */
    private function menu(?SodaModel $soda = null): TestResponse
    {
        return $this->getJson('/api/v1/sodas/'.($soda ?? $this->soda)->id.'/platos');
    }

    /** Place an order of some portions of a dish as a customer, through the API. */
    private function placeOrderFor(DishModel $dish, int $quantity): TestResponse
    {
        Sanctum::actingAs(UserModel::factory()->create(), Role::Customer->abilities());

        return $this->postJson(
            "/api/v1/sodas/{$this->soda->id}/pedidos",
            ['lineas' => [['plato_id' => $dish->id, 'cantidad' => $quantity]]],
            ['Idempotency-Key' => 'toque-1'],
        );
    }
}
