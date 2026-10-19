<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Reporting\Sales\Application\Contracts\DailySalesSummary;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\CommitsRows;
use Tests\TestCase;

final class RefreshDailySalesTest extends TestCase
{
    use CommitsRows {
        tearDown as private truncateCommittedRows;
    }

    private SodaModel $soda;

    /** A soda delivered one order of 5000 colones on the morning of October 6th, Costa Rica time; the summary is stale. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-06 08:00:00');
        $this->soda = SodaModel::factory()->create();
        $this->committedFor($this->soda->id, function (): void {
            $order = OrderModel::factory()->delivered()->create(['soda_id' => $this->soda->id]);
            OrderLineModel::factory()->for($order, 'order')->create(['unit_price' => 2500, 'quantity' => 2]);
        });
    }

    /** The command brings the summary up to date and records when it did, in the same transaction. */
    public function test_command_refreshes_the_summary_and_records_the_moment(): void
    {
        $this->assertSame([], $this->summary());

        $this->artisan('sodaya:refresh-daily-sales')
            ->expectsOutputToContain('El resumen diario de ventas de todas las sodas quedó al día.')
            ->assertSuccessful();

        $this->assertSame([['day' => '2026-10-06', 'orders' => 1, 'total' => 5000]], $this->summary());
        $this->assertSame('2026-10-06T08:00:00-06:00', $this->refreshedAt());
    }

    /** While the maintenance role refreshes, the application keeps reading the summary without waiting. */
    public function test_refresh_does_not_block_the_readers(): void
    {
        $maintenance = DB::connection('pgsql_maintenance');
        $maintenance->beginTransaction();

        try {
            $this->app->make(DailySalesSummary::class)->refresh(now()->toDateTimeImmutable());

            $rows = $this->committedFor($this->soda->id, function (): array {
                DB::statement("SET LOCAL lock_timeout = '1s'");

                return $this->summary();
            });
        } finally {
            $maintenance->rollBack();
        }

        $this->assertSame([], $rows);
        $this->assertNull($this->refreshedAt());
    }

    /** The application role can read when the summary was refreshed, but cannot write it. */
    #[DataProvider('writes')]
    public function test_application_role_cannot_fake_a_refresh(string $sql): void
    {
        try {
            DB::statement($sql, ['2026-10-06 08:00:00-06']);
            $this->fail('The application role wrote a refresh.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
        }
    }

    /**
     * Writes the application role cannot do on the refreshes table.
     *
     * @return array<string, array{string}>
     */
    public static function writes(): array
    {
        return [
            'insert' => ["INSERT INTO materialized_view_refreshes (view_name, refreshed_at) VALUES ('mv_daily_sales', ?)"],
            'update' => ['UPDATE materialized_view_refreshes SET refreshed_at = ?'],
        ];
    }

    /**
     * The refresh runs every quarter of an hour in Costa Rica time, never twice at the same time. The scheduler
     * starts the command in another process, which keeps the real clock, so the test looks at the rows and not at the moment.
     */
    public function test_refresh_runs_every_fifteen_minutes_without_overlapping(): void
    {
        $event = $this->refreshEvent();

        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertSame('America/Costa_Rica', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);

        $this->travelTo(Carbon::create(2026, 10, 6, 10, 14, 0, 'America/Costa_Rica'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame([], $this->summary());
        $this->assertNull($this->refreshedAt());

        $this->travelTo(Carbon::create(2026, 10, 6, 10, 15, 0, 'America/Costa_Rica'));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame([['day' => '2026-10-06', 'orders' => 1, 'total' => 5000]], $this->summary());
        $this->assertNotNull($this->refreshedAt());

        $this->committedFor($this->soda->id, function (): void {
            $order = OrderModel::factory()->delivered()->create(['soda_id' => $this->soda->id]);
            OrderLineModel::factory()->for($order, 'order')->create(['unit_price' => 1000, 'quantity' => 1]);
        });
        $this->travelTo(Carbon::create(2026, 10, 6, 10, 30, 0, 'America/Costa_Rica'));
        $event->mutex->create($event);
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame([['day' => '2026-10-06', 'orders' => 1, 'total' => 5000]], $this->summary());

        $event->mutex->forget($event);
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame([['day' => '2026-10-06', 'orders' => 2, 'total' => 6000]], $this->summary());
    }

    /** The scheduled event of the refresh. */
    private function refreshEvent(): Event
    {
        $events = array_filter(
            $this->app->make(Schedule::class)->events(),
            fn (Event $event): bool => str_contains((string) $event->command, 'sodaya:refresh-daily-sales'),
        );

        $this->assertCount(1, $events);

        return array_values($events)[0];
    }

    /**
     * Rows of the summary of the soda, read as the application role.
     *
     * @return list<array{day: string, orders: int, total: int}>
     */
    private function summary(): array
    {
        return $this->committedFor($this->soda->id, fn (): array => DB::table('v_daily_sales')
            ->orderBy('day')
            ->get(['day', 'orders', 'total'])
            ->map(fn (object $row): array => (array) $row)
            ->all());
    }

    /** Moment of the last refresh in ISO 8601, read as the application role; null if the summary was never refreshed. */
    private function refreshedAt(): ?string
    {
        $moment = DB::table('materialized_view_refreshes')->where('view_name', 'mv_daily_sales')->value('refreshed_at');

        return is_string($moment) ? Carbon::parse($moment)->toIso8601String() : null;
    }

    /** Forget the refreshes too: truncating sodas and users does not reach that table. */
    protected function tearDown(): void
    {
        DB::connection('pgsql_admin')->table('materialized_view_refreshes')->delete();

        $this->truncateCommittedRows();
    }
}
