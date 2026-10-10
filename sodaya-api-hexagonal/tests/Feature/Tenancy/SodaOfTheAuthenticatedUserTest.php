<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class SodaOfTheAuthenticatedUserTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private SodaModel $otherSoda;

    /** Authenticate the owner of one soda and create another soda the requests try to reach. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 09:00:00');
        $this->actOnNewSoda();
        $this->otherSoda = SodaModel::factory()->create();
    }

    /** No staff route carries a soda in its path. */
    public function test_no_staff_route_takes_the_soda_from_the_path(): void
    {
        $staff = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'api/v1/cocina'));

        $this->assertNotEmpty($staff);

        foreach ($staff as $route) {
            $this->assertNotContains('soda', $route->parameterNames(), $route->uri());
        }
    }

    /** A new row belongs to the soda of the user, whatever soda the body and the query string name. */
    #[DataProvider('creations')]
    public function test_created_row_belongs_to_the_soda_of_the_user(string $endpoint, array $payload, string $table): void
    {
        $other = $this->otherSoda->id;

        $this->postJson("{$endpoint}?soda_id={$other}&soda={$other}", [...$payload, 'soda_id' => $other, 'soda' => $other])
            ->assertCreated();

        $this->assertDatabaseHas($table, ['soda_id' => $this->soda->id]);
        $this->assertDatabaseMissing($table, ['soda_id' => $other]);
    }

    /**
     * Endpoints that create a row, a valid payload and the table that stores it.
     *
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function creations(): array
    {
        return [
            'dish' => ['/api/v1/cocina/platos', [
                'nombre' => 'Casado de pollo',
                'precio' => 2800,
                'minutos_preparacion' => 15,
                'porciones_disponibles' => 10,
            ], 'dishes'],
            'category' => ['/api/v1/cocina/categorias', ['nombre' => 'Casados'], 'categories'],
            'time slot' => ['/api/v1/cocina/horario', ['dia' => 1, 'abre' => '08:00', 'cierra' => '12:00'], 'schedules'],
            'closure' => ['/api/v1/cocina/cierres', ['fecha' => '2026-12-25'], 'closures'],
            'closure for today' => ['/api/v1/cocina/cierres/hoy', [], 'closures'],
        ];
    }

    /** A listing shows the rows of the soda of the user, whatever soda the query string names. */
    #[DataProvider('listings')]
    public function test_listing_ignores_a_soda_in_the_query_string(string $endpoint, string $model): void
    {
        $own = $model::factory()->create(['soda_id' => $this->soda->id]);
        $model::factory()->create(['soda_id' => $this->otherSoda->id]);
        $other = $this->otherSoda->id;

        $this->getJson("{$endpoint}?soda_id={$other}&soda={$other}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->getKey());
    }

    /**
     * Listing endpoints paired with the model of the rows they show.
     *
     * @return array<string, array{string, class-string<Model>}>
     */
    public static function listings(): array
    {
        return [
            'dishes' => ['/api/v1/cocina/platos', DishModel::class],
            'categories' => ['/api/v1/cocina/categorias', CategoryModel::class],
            'opening hours' => ['/api/v1/cocina/horario', TimeSlotModel::class],
            'closures' => ['/api/v1/cocina/cierres', ClosureModel::class],
        ];
    }

    /** A customer has no soda, so every staff listing answers the forbidden problem. */
    public function test_customer_cannot_use_the_staff_endpoints(): void
    {
        Sanctum::actingAs(UserModel::factory()->create(), ['pedidos']);

        foreach (self::listings() as [$endpoint]) {
            $this->getJson($endpoint)
                ->assertForbidden()
                ->assertHeader('Content-Type', 'application/problem+json')
                ->assertJsonPath('type', 'http://localhost/problemas/prohibido');
        }
    }
}
