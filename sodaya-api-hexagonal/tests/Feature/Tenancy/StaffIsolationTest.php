<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\TestImages;
use Tests\TestCase;

final class StaffIsolationTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string MISSING_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    private SodaModel $otherSoda;

    /** @var array<string, Model> */
    private array $own;

    /** @var array<string, Model> */
    private array $foreign;

    /** Give the authenticated owner and another soda one row of each kind on a Monday morning. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 09:00:00');
        $this->actOnNewSoda();
        $this->otherSoda = SodaModel::factory()->create();

        $this->own = $this->rowsOf($this->soda, 'Bebidas', 'Fresco de cas', 2, '2026-12-25');
        $this->foreign = $this->rowsOf($this->otherSoda, 'Casados', 'Casado de pollo', 1, '2026-10-05');
    }

    /** A staff listing shows the rows of the own soda and none of the other. */
    #[DataProvider('listings')]
    public function test_listing_shows_only_the_rows_of_the_own_soda(string $endpoint, string $kind): void
    {
        $this->getJson($endpoint)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->own[$kind]->getKey()]);
    }

    /**
     * Listing endpoints paired with the kind of row they show.
     *
     * @return array<string, array{string, string}>
     */
    public static function listings(): array
    {
        return [
            'list dishes' => ['/api/v1/cocina/platos', 'dish'],
            'list categories' => ['/api/v1/cocina/categorias', 'category'],
            'list opening hours' => ['/api/v1/cocina/horario', 'slot'],
            'list closures' => ['/api/v1/cocina/cierres', 'closure'],
            'list orders' => ['/api/v1/cocina/pedidos', 'order'],
            'list promotions' => ['/api/v1/cocina/promociones', 'promotion'],
        ];
    }

    /** A row of the other soda answers the same 404 as a row that does not exist, and stays as it was. */
    #[DataProvider('changesById')]
    public function test_foreign_row_answers_like_a_missing_one(string $method, string $endpoint, string $kind, array $payload): void
    {
        $before = $this->rowsOfTheOtherSoda();

        $foreignResponse = $this->json($method, $endpoint.'/'.$this->foreign[$kind]->getKey(), $payload);
        $missingResponse = $this->json($method, $endpoint.'/'.self::MISSING_ID, $payload);

        $foreignResponse
            ->assertNotFound()
            ->assertJsonPath('type', 'http://localhost/problemas/no-encontrado');
        $this->assertSame(
            Arr::except($missingResponse->json(), 'instance'),
            Arr::except($foreignResponse->json(), 'instance'),
        );
        $this->assertSame($before, $this->rowsOfTheOtherSoda());
    }

    /** The photo of a dish of the other soda answers the same 404 as a missing dish and stores nothing. */
    public function test_photo_of_a_foreign_dish_answers_like_a_missing_one(): void
    {
        Storage::fake('public');
        $before = $this->rowsOfTheOtherSoda();

        $foreignResponse = $this->post("/api/v1/cocina/platos/{$this->foreign['dish']->getKey()}/foto", ['foto' => TestImages::png()], ['Accept' => 'application/json']);
        $missingResponse = $this->post('/api/v1/cocina/platos/'.self::MISSING_ID.'/foto', ['foto' => TestImages::png()], ['Accept' => 'application/json']);

        $foreignResponse->assertNotFound();
        $this->assertSame(
            Arr::except($missingResponse->json(), 'instance'),
            Arr::except($foreignResponse->json(), 'instance'),
        );
        $this->assertSame($before, $this->rowsOfTheOtherSoda());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    /**
     * Endpoints that change or delete a row by its identifier, the kind of row and a valid payload.
     *
     * @return array<string, array{string, string, string, array<string, mixed>}>
     */
    public static function changesById(): array
    {
        return [
            'update a dish' => ['PATCH', '/api/v1/cocina/platos', 'dish', ['precio' => 3000, 'activo' => false]],
            'rename a category' => ['PATCH', '/api/v1/cocina/categorias', 'category', ['nombre' => 'Platos fuertes']],
            'delete a category' => ['DELETE', '/api/v1/cocina/categorias', 'category', []],
            'delete a time slot' => ['DELETE', '/api/v1/cocina/horario', 'slot', []],
            'delete a closure' => ['DELETE', '/api/v1/cocina/cierres', 'closure', []],
            'delete a promotion' => ['DELETE', '/api/v1/cocina/promociones', 'promotion', []],
        ];
    }

    /** Creating a row with the values the other soda already uses succeeds in the own soda and leaves the other intact. */
    #[DataProvider('creations')]
    public function test_creation_ignores_and_keeps_the_rows_of_the_other_soda(string $endpoint, array $payload, string $table): void
    {
        $before = $this->rowsOfTheOtherSoda();

        $rowId = $this->postJson($endpoint, $payload)->assertCreated()->json('data.id');

        $this->assertDatabaseHas($table, ['id' => $rowId, 'soda_id' => $this->soda->id]);
        $this->assertSame($before, $this->rowsOfTheOtherSoda());
    }

    /**
     * Endpoints that create a row, a payload that repeats a row of the other soda and the table that stores it.
     *
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    public static function creations(): array
    {
        return [
            'create a dish' => ['/api/v1/cocina/platos', [
                'nombre' => 'Casado de pollo',
                'precio' => 2800,
                'minutos_preparacion' => 15,
                'porciones_disponibles' => 10,
            ], 'dishes'],
            'create a category' => ['/api/v1/cocina/categorias', ['nombre' => 'Casados'], 'categories'],
            'add a time slot' => ['/api/v1/cocina/horario', ['dia' => 1, 'abre' => '08:00', 'cierra' => '12:00'], 'schedules'],
            'register a closure' => ['/api/v1/cocina/cierres', ['fecha' => '2026-10-05'], 'closures'],
            'close for today' => ['/api/v1/cocina/cierres/hoy', ['motivo' => 'Se acabó la comida'], 'closures'],
            'define a promotion' => ['/api/v1/cocina/promociones', ['dia' => 1, 'inicio' => '14:00', 'fin' => '15:00', 'porcentaje' => 20], 'promotions'],
        ];
    }

    /** A dish cannot point to a category of the other soda; the answer equals the one for a missing category. */
    #[DataProvider('dishWrites')]
    public function test_dish_cannot_reference_a_category_of_the_other_soda(string $method, bool $existing): void
    {
        $endpoint = '/api/v1/cocina/platos'.($existing ? '/'.$this->own['dish']->getKey() : '');
        $payload = ['nombre' => 'Agua dulce', 'precio' => 800, 'minutos_preparacion' => 5, 'porciones_disponibles' => 20];

        $foreignResponse = $this->json($method, $endpoint, [...$payload, 'categoria_id' => $this->foreign['category']->getKey()]);
        $missingResponse = $this->json($method, $endpoint, [...$payload, 'categoria_id' => self::MISSING_ID]);

        $foreignResponse->assertUnprocessable()->assertJsonPath('errores.categoria_id.0', 'El valor seleccionado para categoría no es válido.');
        $this->assertSame(
            Arr::except($missingResponse->json(), 'instance'),
            Arr::except($foreignResponse->json(), 'instance'),
        );
    }

    /**
     * Requests that write a dish: whether they target an existing one.
     *
     * @return array<string, array{string, bool}>
     */
    public static function dishWrites(): array
    {
        return [
            'create a dish' => ['POST', false],
            'update a dish' => ['PATCH', true],
        ];
    }

    /**
     * Every row of the other soda, table by table, read under that soda.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function rowsOfTheOtherSoda(): array
    {
        $this->scopeQueriesTo($this->otherSoda->id);
        $rows = [];

        foreach (['categories', 'dishes', 'schedules', 'closures', 'promotions'] as $table) {
            $rows[$table] = DB::table($table)
                ->where('soda_id', $this->otherSoda->id)
                ->orderBy('id')
                ->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
        }

        return $rows;
    }

    /**
     * Create a category, a dish in it, a time slot, a closure, an order and a promotion for a soda.
     *
     * @return array<string, Model>
     */
    private function rowsOf(SodaModel $soda, string $category, string $dish, int $day, string $closedOn): array
    {
        $categoryRow = CategoryModel::factory()->create(['soda_id' => $soda->id, 'name' => $category]);

        return [
            'category' => $categoryRow,
            'dish' => DishModel::factory()->create([
                'soda_id' => $soda->id,
                'category_id' => $categoryRow->id,
                'name' => $dish,
            ]),
            'slot' => TimeSlotModel::factory()->create([
                'soda_id' => $soda->id,
                'day_of_week' => $day,
                'opens_at' => '08:00',
                'closes_at' => '12:00',
            ]),
            'closure' => ClosureModel::factory()->create(['soda_id' => $soda->id, 'closed_on' => $closedOn]),
            'order' => OrderModel::factory()->create(['soda_id' => $soda->id]),
            'promotion' => PromotionModel::factory()->create(['soda_id' => $soda->id, 'weekday' => $day]),
        ];
    }
}
