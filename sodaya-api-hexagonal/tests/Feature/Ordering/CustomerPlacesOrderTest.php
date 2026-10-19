<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class CustomerPlacesOrderTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string MISSING_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    private SodaModel $soda;

    private UserModel $customer;

    private DishModel $casado;

    private DishModel $fresco;

    /** A soda open on Monday from 6:00 to 14:00 with 3 casados and 10 frescos, and a customer ordering at noon. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->soda = SodaModel::factory()->create();
        TimeSlotModel::factory()->create(['soda_id' => $this->soda->id, 'day_of_week' => 1, 'opens_at' => '06:00', 'closes_at' => '14:00']);
        $this->casado = DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Casado de pollo', 'price' => 2_800, 'available_portions' => 3]);
        $this->fresco = DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Fresco de cas', 'price' => 1_000, 'available_portions' => 10]);
        $this->customer = UserModel::factory()->create();

        Sanctum::actingAs($this->customer, Role::Customer->abilities());
        $this->withHeader('Idempotency-Key', 'toque-1');
    }

    /** Without the key of the request, the order cannot be told apart from a retry and is refused. */
    #[DataProvider('missingKeys')]
    public function test_request_without_a_valid_key_is_refused(?string $key): void
    {
        $this->flushHeaders();
        $headers = $key === null ? [] : ['Idempotency-Key' => $key];

        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 1]]], $headers)
            ->assertBadRequest()
            ->assertJsonPath('type', 'http://localhost/problemas/solicitud-invalida');

        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * Keys that do not identify a request.
     *
     * @return array<string, array{string|null}>
     */
    public static function missingKeys(): array
    {
        return ['no header' => [null], 'blank key' => ['  '], 'key too long' => [str_repeat('k', 101)]];
    }

    /** The same request sent again answers 200 with the order already created and takes no portion twice. */
    public function test_repeated_request_answers_the_same_order(): void
    {
        $payload = ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 2]]];

        $first = $this->postJson($this->endpoint(), $payload)->assertCreated();
        $again = $this->postJson($this->endpoint(), $payload)->assertOk();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame($first->headers->get('Location'), $again->headers->get('Location'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $this->casado->refresh()->available_portions);
    }

    /** The same key with other lines is a conflict. */
    public function test_same_key_with_other_lines_is_a_conflict(): void
    {
        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 2]]])->assertCreated();

        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 1]]])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/conflicto')
            ->assertJsonPath('detail', 'Ya existe un pedido con esa clave de idempotencia y otro contenido.');

        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(1, $this->casado->refresh()->available_portions);
    }

    /** The order is created pending, identified with a UUID v7 and announced in the Location header. */
    public function test_customer_places_an_order(): void
    {
        $response = $this->postJson($this->endpoint(), ['lineas' => [
            ['plato_id' => $this->casado->id, 'cantidad' => 2],
            ['plato_id' => $this->fresco->id, 'cantidad' => 1],
        ]]);

        $response
            ->assertCreated()
            ->assertHeader('Location', 'http://localhost/api/v1/pedidos/'.$response->json('data.id'))
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.pago.estado', 'pendiente')
            ->assertJsonPath('data.lineas.0.nombre', 'Casado de pollo')
            ->assertJsonPath('data.lineas.0.precio_unitario', 2_800)
            ->assertJsonPath('data.lineas.0.subtotal', 5_600)
            ->assertJsonPath('data.total', 6_600)
            ->assertJsonPath('data.creado_en', '2026-10-05T12:00:00-06:00');

        $this->assertTrue(Str::isUuid($response->json('data.id'), 7));
        $this->assertDatabaseHas('orders', ['id' => $response->json('data.id'), 'customer_id' => $this->customer->id, 'soda_id' => $this->soda->id]);
        $this->assertSame(1, $this->casado->refresh()->available_portions);
    }

    /** An order without lines has its own problem type. */
    public function test_order_without_lines_is_empty(): void
    {
        $this->postJson($this->endpoint(), ['lineas' => []])
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/pedido-vacio')
            ->assertJsonPath('detail', 'El pedido debe tener al menos una línea.');

        $this->assertDatabaseCount('orders', 0);
    }

    /** A quantity outside 1 to 20 has its own problem type. */
    #[DataProvider('invalidQuantities')]
    public function test_quantity_outside_the_range_is_invalid(int $quantity): void
    {
        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => $quantity]]])
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/cantidad-invalida')
            ->assertJsonPath('detail', 'La cantidad de cada línea debe estar entre 1 y 20.');

        $this->assertSame(3, $this->casado->refresh()->available_portions);
    }

    /**
     * Quantities just outside the range.
     *
     * @return array<string, array{int}>
     */
    public static function invalidQuantities(): array
    {
        return ['zero' => [0], 'twenty-one' => [21]];
    }

    /** More than 20 lines, a repeated dish or a malformed line are invalid data. */
    #[DataProvider('invalidPayloads')]
    public function test_malformed_order_is_invalid_data(string $case, string $detail): void
    {
        $lines = match ($case) {
            'too many lines' => array_map(
                fn (DishModel $dish): array => ['plato_id' => $dish->id, 'cantidad' => 1],
                DishModel::factory()->count(21)->create(['soda_id' => $this->soda->id])->all(),
            ),
            'repeated dish' => [['plato_id' => $this->casado->id, 'cantidad' => 1], ['plato_id' => $this->casado->id, 'cantidad' => 2]],
            default => [['plato_id' => 'casado', 'cantidad' => 'dos']],
        };

        $this->postJson($this->endpoint(), ['lineas' => $lines])
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath('detail', $detail);
    }

    /**
     * Payloads the shape rules or the domain reject as invalid data.
     *
     * @return array<string, array{string, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'too many lines' => ['too many lines', 'El pedido debe tener entre 1 y 20 líneas.'],
            'repeated dish' => ['repeated dish', 'Un plato solo puede aparecer en una línea del pedido.'],
            'malformed line' => ['malformed line', 'Los datos enviados no son válidos.'],
        ];
    }

    /** A malformed line reports the errors of each field in Spanish. */
    public function test_malformed_line_reports_each_field(): void
    {
        $errors = $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => 'casado', 'cantidad' => 'dos']]])
            ->assertUnprocessable()
            ->json('errores');

        $this->assertSame(['El campo plato debe ser un UUID válido.'], $errors['lineas.0.plato_id']);
        $this->assertSame(['El campo cantidad debe ser un número entero.'], $errors['lineas.0.cantidad']);
    }

    /** A dish of another soda is refused, even if it exists. */
    public function test_dish_of_another_soda_is_refused(): void
    {
        $foreign = DishModel::factory()->create();

        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $foreign->id, 'cantidad' => 1]]])
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/plato-no-pertenece-a-la-soda');
    }

    /** A dish taken off the menu is refused. */
    public function test_inactive_dish_is_refused(): void
    {
        $retired = DishModel::factory()->inactive()->create(['soda_id' => $this->soda->id]);

        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $retired->id, 'cantidad' => 1]]])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/plato-no-disponible');
    }

    /** Outside the schedule, or on a closure, the soda takes no order. */
    public function test_closed_soda_takes_no_order(): void
    {
        $this->travelTo('2026-10-05 15:00:00');
        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 1]]])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/soda-cerrada');

        $this->travelTo('2026-10-05 12:00:00');
        ClosureModel::factory()->create(['soda_id' => $this->soda->id, 'closed_on' => '2026-10-05']);
        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 1]]])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/soda-cerrada');

        $this->assertDatabaseCount('orders', 0);
    }

    /** Asking for more portions than the soda has left is refused and nothing is stored. */
    public function test_sold_out_dish_is_refused(): void
    {
        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 4]]])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/porciones-agotadas');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(3, $this->casado->refresh()->available_portions);
    }

    /** An unknown soda is not found. */
    public function test_unknown_soda_is_not_found(): void
    {
        $this->postJson('/api/v1/sodas/'.self::MISSING_ID.'/pedidos', ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 1]]])
            ->assertNotFound()
            ->assertJsonPath('type', 'http://localhost/problemas/no-encontrado');
    }

    /** Only a customer orders: the staff is forbidden and a request without token is not authenticated. */
    public function test_only_customers_place_orders(): void
    {
        Sanctum::actingAs(UserModel::factory()->kitchen()->create(), Role::Kitchen->abilities());
        $this->postJson($this->endpoint(), ['lineas' => [['plato_id' => $this->casado->id, 'cantidad' => 1]]])
            ->assertForbidden()
            ->assertJsonPath('type', 'http://localhost/problemas/prohibido');

        $this->app['auth']->forgetGuards();
        $this->postJson($this->endpoint(), ['lineas' => []], ['Authorization' => ''])
            ->assertUnauthorized();
    }

    /** Address of the orders of the soda. */
    private function endpoint(): string
    {
        return "/api/v1/sodas/{$this->soda->id}/pedidos";
    }
}
