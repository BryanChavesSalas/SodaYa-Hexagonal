<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use Database\Factories\OrderFactory;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Reporting\Sales\Application\UseCases\RefreshDailySales;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\CommitsRows;
use Tests\TestCase;

final class DailySalesTest extends TestCase
{
    use ActsOnSoda, CommitsRows {
        CommitsRows::tearDown as private truncateCommittedRows;
    }

    private const string ENDPOINT = '/api/v1/cocina/reportes/ventas-diarias';

    private SodaModel $otherSoda;

    /**
     * On the morning of October 6th, Costa Rica time, the owner of a soda has three delivered orders, two on the 5th
     * and one ten minutes after midnight on the 6th, plus a cancelled one; another soda delivered one on the 5th.
     * The rows are committed, because the summary is refreshed by another connection.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 08:00:00');
        $this->actOnNewSoda();
        $this->otherSoda = SodaModel::factory()->create();

        $this->order($this->soda, OrderModel::factory()->delivered(), '2026-10-05 11:10:00', 2800, 2);
        $this->order($this->soda, OrderModel::factory()->delivered(), '2026-10-05 23:30:00', 2000, 1);
        $this->order($this->soda, OrderModel::factory()->delivered(), '2026-10-06 00:10:00', 1500, 1);
        $this->order($this->soda, OrderModel::factory()->cancelled(), '2026-10-05 12:00:00', 9000, 1);
        $this->order($this->otherSoda, OrderModel::factory()->delivered(), '2026-10-05 11:15:00', 9000, 1);
    }

    /** The owner sees, for each day of the range with deliveries, how many orders and how much they sold, and when the summary was computed. */
    public function test_owner_sees_the_delivered_orders_and_sales_of_each_day(): void
    {
        $this->refresh();

        $this->getJson(self::ENDPOINT.'?desde=2026-10-01&hasta=2026-10-31')
            ->assertOk()
            ->assertExactJson(['data' => [
                'desde' => '2026-10-01',
                'hasta' => '2026-10-31',
                'actualizado_en' => '2026-10-06T08:00:00-06:00',
                'dias' => [
                    ['fecha' => '2026-10-05', 'pedidos' => 2, 'total' => 7600],
                    ['fecha' => '2026-10-06', 'pedidos' => 1, 'total' => 1500],
                ],
            ]]);
    }

    /** Both ends of the range are included and the days outside it are left out. */
    public function test_days_outside_the_range_are_left_out(): void
    {
        $this->refresh();

        $this->getJson(self::ENDPOINT.'?desde=2026-10-06&hasta=2026-10-06')
            ->assertOk()
            ->assertJsonPath('data.dias', [['fecha' => '2026-10-06', 'pedidos' => 1, 'total' => 1500]]);
    }

    /** Until the summary is computed for the first time, the report is empty and says so with a null moment. */
    public function test_report_is_empty_until_the_summary_is_computed(): void
    {
        $this->getJson(self::ENDPOINT.'?desde=2026-10-01&hasta=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.actualizado_en', null)
            ->assertJsonPath('data.dias', []);
    }

    /** The owner of another soda sees the days of that soda and nothing of the first. */
    public function test_another_soda_sees_only_its_own_days(): void
    {
        $this->refresh();
        $this->actAsStaffOf($this->otherSoda);

        $this->getJson(self::ENDPOINT.'?desde=2026-10-01&hasta=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.dias', [['fecha' => '2026-10-05', 'pedidos' => 1, 'total' => 9000]]);
    }

    /** The kitchen cannot see the report. */
    public function test_kitchen_has_no_access(): void
    {
        $this->actAsStaffOf($this->soda, Role::Kitchen);

        $this->getJson(self::ENDPOINT.'?desde=2026-10-01&hasta=2026-10-31')
            ->assertForbidden()
            ->assertJsonPath('type', 'http://localhost/problemas/prohibido');
    }

    /** A range of exactly 366 days is the longest one accepted. */
    public function test_range_of_a_year_is_accepted(): void
    {
        $this->getJson(self::ENDPOINT.'?desde=2025-10-01&hasta=2026-10-01')->assertOk();
    }

    /** Missing, malformed, reversed or too long ranges are reported per field in Spanish. */
    #[DataProvider('invalidRanges')]
    public function test_invalid_ranges_are_rejected(string $query, string $field, string $message): void
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
    public static function invalidRanges(): array
    {
        return [
            'start missing' => ['hasta=2026-10-31', 'desde', 'El campo fecha inicial es obligatorio.'],
            'start in another format' => ['desde=05/10/2026&hasta=2026-10-31', 'desde', 'El campo fecha inicial debe tener el formato Y-m-d.'],
            'end before start' => ['desde=2026-10-31&hasta=2026-10-01', 'hasta', 'La fecha final no puede ser anterior a la inicial.'],
            'range longer than a year' => ['desde=2025-09-30&hasta=2026-10-01', 'hasta', 'El rango no puede abarcar más de 366 días.'],
        ];
    }

    /** The demo owner sees the delivered demo order the day the data was seeded, because the seeder refreshes the summary. */
    public function test_demo_owner_sees_the_delivered_demo_order(): void
    {
        $this->seed();
        Sanctum::actingAs(UserModel::query()->where('email', 'duena@sodaya.test')->firstOrFail(), Role::Owner->abilities());

        $this->getJson(self::ENDPOINT.'?desde=2026-10-06&hasta=2026-10-06')
            ->assertOk()
            ->assertJsonPath('data.actualizado_en', '2026-10-06T08:00:00-06:00')
            ->assertJsonPath('data.dias', [['fecha' => '2026-10-06', 'pedidos' => 1, 'total' => 4600]]);
    }

    /** Bring the summary up to date, as the scheduler does. */
    private function refresh(): void
    {
        $this->app->make(RefreshDailySales::class)->execute(now()->toDateTimeImmutable());
    }

    /** Create a committed order with one line, created at a Costa Rica time. */
    private function order(SodaModel $soda, OrderFactory $factory, string $createdAt, int $unitPrice, int $quantity): void
    {
        $this->committedFor($soda->id, function () use ($soda, $factory, $createdAt, $unitPrice, $quantity): void {
            $order = $factory->create(['soda_id' => $soda->id, 'created_at' => $createdAt]);
            OrderLineModel::factory()->for($order, 'order')->create(['unit_price' => $unitPrice, 'quantity' => $quantity]);
        });
    }

    /** Forget the refreshes too: truncating sodas and users does not reach that table. */
    protected function tearDown(): void
    {
        DB::connection('pgsql_admin')->table('materialized_view_refreshes')->delete();

        $this->truncateCommittedRows();
    }
}
