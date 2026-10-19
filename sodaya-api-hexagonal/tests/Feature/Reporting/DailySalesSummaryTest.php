<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use Database\Factories\OrderFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\CommitsRows;
use Tests\TestCase;

final class DailySalesSummaryTest extends TestCase
{
    use CommitsRows;

    private const string REPORT_QUERY = 'SELECT day, orders, total FROM v_daily_sales WHERE soda_id = ? AND day BETWEEN ?::date AND ?::date ORDER BY day';

    private SodaModel $soda;

    private SodaModel $otherSoda;

    /**
     * Two sodas with committed orders, in Costa Rica time: the first delivers two on October 5th, one of them at
     * 23:30, and one ten minutes after midnight on the 6th, and has a cancelled and a confirmed order; the second
     * delivers one on the 5th. Then the summary is refreshed.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->soda = SodaModel::factory()->create();
        $this->otherSoda = SodaModel::factory()->create();

        $this->order($this->soda, OrderModel::factory()->delivered(), '2026-10-05 11:10:00', 2800, 2);
        $this->order($this->soda, OrderModel::factory()->delivered(), '2026-10-05 23:30:00', 2000, 1);
        $this->order($this->soda, OrderModel::factory()->delivered(), '2026-10-06 00:10:00', 1500, 1);
        $this->order($this->soda, OrderModel::factory()->cancelled(), '2026-10-05 12:00:00', 9000, 1);
        $this->order($this->soda, OrderModel::factory()->confirmed(), '2026-10-05 12:30:00', 9000, 1);
        $this->order($this->otherSoda, OrderModel::factory()->delivered(), '2026-10-05 11:15:00', 9000, 1);

        $this->refresh();
    }

    /** The summary has one row per soda and Costa Rica day, with the delivered orders and the sum of their lines. */
    public function test_summary_adds_up_the_delivered_orders_of_each_day(): void
    {
        $this->assertSame([
            ['day' => '2026-10-05', 'orders' => 2, 'total' => 7600],
            ['day' => '2026-10-06', 'orders' => 1, 'total' => 1500],
        ], $this->summaryOf($this->soda->id));
    }

    /** The view shows the rows of the soda of the transaction and none of another; without a soda, none at all. */
    public function test_view_shows_only_the_soda_of_the_transaction(): void
    {
        $this->assertSame([['day' => '2026-10-05', 'orders' => 1, 'total' => 9000]], $this->summaryOf($this->otherSoda->id));
        $this->assertSame([], $this->summaryOf(null));
    }

    /** The view filters by the soda itself, behind a security barrier, because a materialized view has no policies. */
    public function test_view_is_a_security_barrier(): void
    {
        $this->assertSame(
            'security_barrier=true',
            DB::scalar("select array_to_string(reloptions, ',') from pg_class where oid = 'v_daily_sales'::regclass"),
        );
    }

    /** The application role can neither read the materialized view, nor call the function that feeds it, nor refresh it. */
    #[DataProvider('forbiddenStatements')]
    public function test_application_role_is_kept_out_of_the_materialized_view(string $sql): void
    {
        try {
            DB::statement($sql);
            $this->fail('The application role reached the materialized view.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
            $this->assertStringContainsString('permission denied', $exception->getMessage());
        }
    }

    /**
     * Statements the application role cannot run.
     *
     * @return array<string, array{string}>
     */
    public static function forbiddenStatements(): array
    {
        return [
            'read the materialized view' => ['SELECT * FROM mv_daily_sales'],
            'call the function of every soda' => ['SELECT * FROM fn_daily_sales_of_every_soda()'],
            'refresh the materialized view' => ['REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_sales'],
        ];
    }

    /** The query of the report reads the materialized view and nothing else: neither orders nor order_lines. */
    public function test_report_query_reads_only_the_materialized_view(): void
    {
        $plan = $this->committedFor($this->soda->id, fn (): array => $this->plan(self::REPORT_QUERY, [$this->soda->id, '2026-10-01', '2026-10-31']));

        $this->assertSame(['mv_daily_sales'], $this->relationsIn($plan));
    }

    /** A delivered order shows up in the summary only after the maintenance role refreshes it, concurrently. */
    public function test_maintenance_role_refreshes_the_summary(): void
    {
        $maintenance = config('database.connections.pgsql_maintenance.username');
        $this->assertTrue(DB::scalar("select has_table_privilege(?, 'mv_daily_sales', 'MAINTAIN')", [$maintenance]));
        $this->assertStringContainsString('UNIQUE INDEX', DB::scalar("select indexdef from pg_indexes where indexname = 'mv_daily_sales_soda_id_day_unique'"));

        $this->order($this->soda, OrderModel::factory()->delivered(), '2026-10-06 12:00:00', 1000, 1);
        $this->assertSame(['day' => '2026-10-06', 'orders' => 1, 'total' => 1500], $this->summaryOf($this->soda->id)[1]);

        $this->refresh();
        $this->assertSame(['day' => '2026-10-06', 'orders' => 2, 'total' => 2500], $this->summaryOf($this->soda->id)[1]);
    }

    /** Refresh the summary as the maintenance role, without blocking the readers. */
    private function refresh(): void
    {
        DB::connection('pgsql_maintenance')->statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_sales');
    }

    /**
     * Rows of the view, read as the application role in a transaction of a soda.
     *
     * @return list<array{day: string, orders: int, total: int}>
     */
    private function summaryOf(?string $sodaId): array
    {
        return app(SodaTransaction::class)->run($sodaId, fn (): array => DB::table('v_daily_sales')
            ->orderBy('day')
            ->get(['day', 'orders', 'total'])
            ->map(fn (object $row): array => (array) $row)
            ->all());
    }

    /** Create a committed order with one line, created at a Costa Rica time. */
    private function order(SodaModel $soda, OrderFactory $factory, string $createdAt, int $unitPrice, int $quantity): void
    {
        $this->committedFor($soda->id, function () use ($soda, $factory, $createdAt, $unitPrice, $quantity): void {
            $order = $factory->create(['soda_id' => $soda->id, 'created_at' => $createdAt]);
            OrderLineModel::factory()->for($order, 'order')->create(['unit_price' => $unitPrice, 'quantity' => $quantity]);
        });
    }

    /**
     * Plan of the query, as the application role would run it.
     *
     * @param  list<mixed>  $bindings
     * @return array<string, mixed>
     */
    private function plan(string $sql, array $bindings): array
    {
        $json = DB::scalar('EXPLAIN (FORMAT JSON) '.$sql, $bindings);
        $explain = json_decode(is_string($json) ? $json : '[]', true, flags: JSON_THROW_ON_ERROR);

        return $explain[0]['Plan'];
    }

    /**
     * Relations the plan reads, at any depth.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function relationsIn(array $node): array
    {
        $relations = isset($node['Relation Name']) && is_string($node['Relation Name']) ? [$node['Relation Name']] : [];

        foreach (is_array($node['Plans'] ?? null) ? $node['Plans'] : [] as $child) {
            $relations = [...$relations, ...$this->relationsIn($child)];
        }

        return $relations;
    }
}
