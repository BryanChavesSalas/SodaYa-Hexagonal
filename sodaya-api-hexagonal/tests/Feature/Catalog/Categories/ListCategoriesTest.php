<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Categories;

use Illuminate\Support\Facades\DB;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ListCategoriesTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/categorias';

    /** Make a fresh soda the current tenant. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actOnNewSoda();
    }

    /** Categories are sorted and limited to the current soda. */
    public function test_staff_lists_categories_of_their_soda(): void
    {
        CategoryModel::factory()->create([
            'soda_id' => $this->soda->id,
            'name' => 'Postres',
        ]);

        CategoryModel::factory()->create([
            'soda_id' => $this->soda->id,
            'name' => 'Bebidas',
        ]);

        CategoryModel::factory()->create([
            'name' => 'Categoría de otra soda',
        ]);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nombre', 'Bebidas')
            ->assertJsonPath('data.1.nombre', 'Postres');
    }

    /** A soda without categories gets an empty list. */
    public function test_soda_without_categories_gets_an_empty_list(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    /** Listing uses a constant number of queries. */
    public function test_listing_runs_a_constant_number_of_queries(): void
    {
        CategoryModel::factory()
            ->count(25)
            ->create(['soda_id' => $this->soda->id]);

        DB::enableQueryLog();

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonCount(25, 'data');

        $this->assertCount(1, DB::getQueryLog());
    }
}
