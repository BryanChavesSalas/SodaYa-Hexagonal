<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Dishes;

use Illuminate\Support\Facades\DB;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ListDishesTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/platos';

    /** Make a fresh soda the current tenant. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();
    }

    /** The listing includes inactive dishes and excludes other sodas. */
    public function test_staff_lists_every_dish_of_their_soda(): void
    {
        DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Olla de carne']);
        DishModel::factory()->inactive()->create(['soda_id' => $this->soda->id, 'name' => 'Arroz con pollo']);
        DishModel::factory()->create(['name' => 'Plato de otra soda']);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nombre', 'Arroz con pollo')
            ->assertJsonPath('data.0.activo', false)
            ->assertJsonPath('data.1.nombre', 'Olla de carne');
    }

    /** A soda without dishes gets an empty list. */
    public function test_soda_without_dishes_gets_an_empty_list(): void
    {
        $this->getJson(self::ENDPOINT)->assertOk()->assertExactJson(['data' => []]);
    }

    /** The number of queries does not grow with the number of dishes. */
    public function test_listing_runs_a_constant_number_of_queries(): void
    {
        DishModel::factory()->count(25)->create(['soda_id' => $this->soda->id]);

        DB::enableQueryLog();
        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(25, 'data');

        $this->assertCount(1, DB::getQueryLog());
    }
}
