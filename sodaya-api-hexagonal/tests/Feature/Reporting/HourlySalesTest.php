<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use Database\Factories\OrderFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class HourlySalesTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/cocina/reportes/ventas-por-hora';

    /**
     * Give the owner of a soda a Monday of orders: four delivered on that day, one delivered the night before,
     * one cancelled, one confirmed and one delivered by another soda. Times are in Costa Rica.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 08:00:00');
        $this->actOnNewSoda();

        $this->order(OrderModel::factory()->delivered(), '2026-10-05 11:10:00', 2800, 2);
        $this->order(OrderModel::factory()->delivered(), '2026-10-05 11:50:00', 1500, 1);
        $this->order(OrderModel::factory()->delivered(), '2026-10-05 12:05:00', 1000, 3);
        $this->order(OrderModel::factory()->delivered(), '2026-10-05 23:30:00', 2000, 1);
        $this->order(OrderModel::factory()->delivered(), '2026-10-04 23:30:00', 9000, 1);
        $this->order(OrderModel::factory()->cancelled(), '2026-10-05 11:20:00', 9000, 1);
        $this->order(OrderModel::factory()->confirmed(), '2026-10-05 12:30:00', 9000, 1);
        $this->order(OrderModel::factory()->delivered(), '2026-10-05 11:15:00', 9000, 1, SodaModel::factory()->create());
    }

    /** Each hour shows its delivered orders and what they sold, by the Costa Rica hour they were created. */
    public function test_report_adds_up_the_delivered_orders_hour_by_hour(): void
    {
        $this->getJson(self::ENDPOINT.'?fecha=2026-10-05')
            ->assertOk()
            ->assertExactJson(['data' => [
                'fecha' => '2026-10-05',
                'minimo_pedidos' => 1,
                'horas' => [
                    ['hora' => 11, 'pedidos' => 2, 'total' => 7100],
                    ['hora' => 12, 'pedidos' => 1, 'total' => 3000],
                    ['hora' => 23, 'pedidos' => 1, 'total' => 2000],
                ],
            ]]);
    }

    /** Hours below the minimum of orders are left out. */
    public function test_hours_below_the_minimum_are_left_out(): void
    {
        $this->getJson(self::ENDPOINT.'?fecha=2026-10-05&minimo_pedidos=2')
            ->assertOk()
            ->assertJsonPath('data.horas', [['hora' => 11, 'pedidos' => 2, 'total' => 7100]]);
    }

    /** Without a date, the report is for today in Costa Rica. */
    public function test_date_defaults_to_today(): void
    {
        $this->travelTo('2026-10-05 23:45:00');

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.fecha', '2026-10-05')
            ->assertJsonCount(3, 'data.horas');
    }

    /** A date without delivered orders gives an empty list. */
    public function test_date_without_sales_is_empty(): void
    {
        $this->getJson(self::ENDPOINT.'?fecha=2026-10-07')
            ->assertOk()
            ->assertJsonPath('data.horas', []);
    }

    /** The kitchen cannot see the report. */
    public function test_kitchen_has_no_access(): void
    {
        $this->actAsStaffOf($this->soda, Role::Kitchen);

        $this->getJson(self::ENDPOINT.'?fecha=2026-10-05')
            ->assertForbidden()
            ->assertJsonPath('type', 'http://localhost/problemas/prohibido');
    }

    /** Malformed filters are reported per field in Spanish. */
    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected(string $query, string $field, string $message): void
    {
        $this->getJson(self::ENDPOINT.'?'.$query)
            ->assertUnprocessable()
            ->assertJsonPath("errores.{$field}.0", $message);
    }

    /**
     * Query strings the report rejects, the field and the message.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function invalidFilters(): array
    {
        return [
            'date in another format' => ['fecha=05/10/2026', 'fecha', 'El campo fecha debe tener el formato Y-m-d.'],
            'minimum of zero' => ['minimo_pedidos=0', 'minimo_pedidos', 'El campo mínimo de pedidos debe estar entre 1 y 1000.'],
            'minimum that is not a number' => ['minimo_pedidos=muchos', 'minimo_pedidos', 'El campo mínimo de pedidos debe ser un número entero.'],
        ];
    }

    /** Create an order with one line, created at a Costa Rica time, by default for the current soda. */
    private function order(OrderFactory $factory, string $createdAt, int $unitPrice, int $quantity, ?SodaModel $soda = null): void
    {
        $order = $factory->create(['soda_id' => ($soda ?? $this->soda)->id, 'created_at' => $createdAt]);
        OrderLineModel::factory()->for($order, 'order')->create(['unit_price' => $unitPrice, 'quantity' => $quantity]);
    }
}
