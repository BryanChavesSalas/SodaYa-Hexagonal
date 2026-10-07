<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Menu;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class PublicMenuTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string MISSING_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    private SodaModel $soda;

    /** Create the soda whose menu is consulted. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->soda = SodaModel::factory()->create(['name' => 'Soda La Esquina']);
    }

    /** A visitor sees the active dishes grouped by category. */
    public function test_visitor_sees_the_menu_grouped_by_category(): void
    {
        $this->travelTo('2026-10-01 12:00:00');

        $category = CategoryModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Casados']);
        $dish = DishModel::factory()->create([
            'soda_id' => $this->soda->id,
            'category_id' => $category->id,
            'name' => 'Casado de pollo',
            'description' => 'Con arroz y frijoles',
            'price' => 2800,
            'preparation_minutes' => 15,
            'available_portions' => 10,
        ]);

        $this->getJson($this->endpoint())
            ->assertOk()
            ->assertExactJson(['data' => [
                'soda' => ['id' => $this->soda->id, 'nombre' => 'Soda La Esquina'], 'abierta' => false,
                'categorias' => [[
                    'id' => $category->id,
                    'nombre' => 'Casados',
                    'platos' => [[
                        'id' => $dish->id,
                        'nombre' => 'Casado de pollo',
                        'descripcion' => 'Con arroz y frijoles',
                        'precio' => 2800,
                        'minutos_preparacion' => 15,
                        'porciones_disponibles' => 10,
                        'agotado' => false,
                        'actualizado_en' => '2026-10-01T12:00:00-06:00',
                    ]],
                ]],
            ]]);
    }

    /** Categories are sorted by name and uncategorized dishes come last. */
    public function test_uncategorized_dishes_are_listed_last_under_otros(): void
    {
        $drinks = CategoryModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Bebidas']);
        $meals = CategoryModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Casados']);
        DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Chifrijo']);
        DishModel::factory()->create(['soda_id' => $this->soda->id, 'category_id' => $meals->id]);
        DishModel::factory()->create(['soda_id' => $this->soda->id, 'category_id' => $drinks->id]);

        $this->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonPath('data.categorias.*.nombre', ['Bebidas', 'Casados', 'Otros'])
            ->assertJsonPath('data.categorias.2.id', null)
            ->assertJsonPath('data.categorias.2.platos.0.nombre', 'Chifrijo');
    }

    /** A sold-out dish stays on the menu and is flagged. */
    public function test_sold_out_dish_is_flagged_instead_of_hidden(): void
    {
        DishModel::factory()->soldOut()->create(['soda_id' => $this->soda->id]);

        $this->getJson($this->endpoint())
            ->assertOk()
            ->assertJsonPath('data.categorias.0.platos.0.agotado', true)
            ->assertJsonPath('data.categorias.0.platos.0.porciones_disponibles', 0);
    }

    /** Inactive dishes and dishes of other sodas never appear. */
    public function test_inactive_and_foreign_dishes_are_hidden(): void
    {
        DishModel::factory()->inactive()->create(['soda_id' => $this->soda->id]);
        DishModel::factory()->create();

        $this->getJson($this->endpoint())->assertOk()->assertExactJson(['data' => [
            'soda' => ['id' => $this->soda->id, 'nombre' => 'Soda La Esquina'], 'abierta' => false,
            'categorias' => [],
        ]]);
    }

    /** The menu is served in full with a constant number of queries. */
    public function test_menu_is_complete_and_runs_a_constant_number_of_queries(): void
    {
        $categories = CategoryModel::factory()->count(3)->create(['soda_id' => $this->soda->id]);
        $categories->each(fn (CategoryModel $category) => DishModel::factory()->count(10)->create([
            'soda_id' => $this->soda->id,
            'category_id' => $category->id,
        ]));

        DB::enableQueryLog();
        $response = $this->getJson($this->endpoint())->assertOk();

        $this->assertCount(30, $response->json('data.categorias.*.platos.*'));
        $this->assertCount(5, DB::getQueryLog());
    }

    /** The soda opens at the opening minute and closes at the closing minute, on every request. */
    public function test_menu_reports_open_only_inside_the_slot(): void
    {
        $this->openWeekdays();

        foreach ([
            '2026-10-05 07:59:00' => false,
            '2026-10-05 08:00:00' => true,
            '2026-10-05 16:59:00' => true,
            '2026-10-05 17:00:00' => false,
            '2026-10-10 10:00:00' => false,
        ] as $moment => $expected) {
            $this->travelTo(CarbonImmutable::parse($moment, 'America/Costa_Rica'));

            $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.abierta', $expected);
        }
    }

    /** An exceptional closure keeps the soda closed whatever its schedule says. */
    public function test_exceptional_closure_closes_the_soda(): void
    {
        $this->openWeekdays();
        DB::table('exceptional_closures')->insert([
            'soda_id' => $this->soda->id,
            'starts_at' => '2026-10-05 00:00:00-06',
            'ends_at' => '2026-10-06 00:00:00-06',
            'reason' => 'Feriado',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00:00', 'America/Costa_Rica'));
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.abierta', false);

        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00:00', 'America/Costa_Rica'));
        $this->getJson($this->endpoint())->assertOk()->assertJsonPath('data.abierta', true);
    }

    /** Give the soda a Monday to Friday slot from 08:00 to 17:00. */
    private function openWeekdays(): void
    {
        foreach (range(1, 5) as $day) {
            DB::table('opening_slots')->insert([
                'soda_id' => $this->soda->id,
                'day_of_week' => $day,
                'opens_at' => 8 * 60,
                'closes_at' => 17 * 60,
            ]);
        }
    }

    /** An unknown soda answers a not-found problem. */
    public function test_unknown_soda_is_not_found(): void
    {
        $this->getJson('/api/v1/sodas/'.self::MISSING_ID.'/platos')
            ->assertNotFound()
            ->assertJsonPath('type', 'http://localhost/problemas/no-encontrado')
            ->assertJsonPath('detail', 'La soda no existe.');
    }

    /** A visitor sees the detail of an active dish. */
    public function test_visitor_sees_the_detail_of_a_dish(): void
    {
        $dish = DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Olla de carne']);

        $this->getJson($this->endpoint($dish->id))
            ->assertOk()
            ->assertJsonPath('data.id', $dish->id)
            ->assertJsonPath('data.nombre', 'Olla de carne');
    }

    /** Inactive, foreign and missing dishes all answer the same 404. */
    public function test_hidden_dishes_are_not_found(): void
    {
        $inactive = DishModel::factory()->inactive()->create(['soda_id' => $this->soda->id]);
        $foreign = DishModel::factory()->create();

        foreach ([$inactive->id, $foreign->id, self::MISSING_ID] as $dishId) {
            $this->getJson($this->endpoint($dishId))
                ->assertNotFound()
                ->assertJsonPath('detail', 'El plato no existe.');
        }
    }

    /** A malformed identifier is treated as an unknown route. */
    public function test_malformed_identifier_is_not_found(): void
    {
        $this->getJson('/api/v1/sodas/abc/platos')->assertNotFound();
    }

    /** Build the menu endpoint, optionally for one dish. */
    private function endpoint(?string $dishId = null): string
    {
        return "/api/v1/sodas/{$this->soda->id}/platos".($dishId === null ? '' : "/{$dishId}");
    }
}
