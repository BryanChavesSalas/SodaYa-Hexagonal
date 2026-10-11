<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\UnfilteredPublicMenuReader;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class PublicMenuIsolationTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** @var array<string, string> */
    private array $dishOfSoda = [];

    /** Give two sodas one dish each and swap the menu reader for one that forgets to filter by soda. */
    protected function setUp(): void
    {
        parent::setUp();

        foreach (SodaModel::factory()->count(2)->create() as $soda) {
            $this->dishOfSoda[$soda->id] = DishModel::factory()->create(['soda_id' => $soda->id])->id;
        }

        $this->app->bind(PublicMenuReader::class, UnfilteredPublicMenuReader::class);
    }

    /** The menu of each soda lists only its own dish, although the reader asks for every dish. */
    public function test_menu_shows_only_the_dishes_of_the_soda_in_the_url(): void
    {
        foreach ($this->dishOfSoda as $sodaId => $dishId) {
            $this->getJson("/api/v1/sodas/{$sodaId}/platos")
                ->assertOk()
                ->assertJsonPath('data.categorias.0.platos.*.id', [$dishId]);
        }
    }

    /** A dish appears only under the URL of its own soda, although the reader looks it up by identifier alone. */
    public function test_dish_of_another_soda_is_not_found_on_the_menu(): void
    {
        [$sodaId, $otherSodaId] = array_keys($this->dishOfSoda);
        $dishId = $this->dishOfSoda[$otherSodaId];

        $this->getJson("/api/v1/sodas/{$otherSodaId}/platos/{$dishId}")->assertOk();
        $this->getJson("/api/v1/sodas/{$sodaId}/platos/{$dishId}")
            ->assertNotFound()
            ->assertJsonPath('type', 'http://localhost/problemas/no-encontrado');
    }
}
