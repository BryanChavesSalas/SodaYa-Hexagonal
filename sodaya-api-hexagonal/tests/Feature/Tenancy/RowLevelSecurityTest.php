<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Catalog\Categories\Infrastructure\Persistence\Models\CategoryModel;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Catalog\Promotions\Infrastructure\Persistence\Models\PromotionModel;
use Src\Sodas\Closures\Infrastructure\Persistence\Models\ClosureModel;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class RowLevelSecurityTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private const string POLICY_EXPRESSION = "(soda_id = (NULLIF(current_setting('app.soda_id'::text, true), ''::text))::uuid)";

    private const string CUSTOMER_EXPRESSION = "(customer_id = (NULLIF(current_setting('app.customer_id'::text, true), ''::text))::uuid)";

    /**
     * Tables of an order, which the customer who placed it may also read.
     *
     * @var list<string>
     */
    private const array ORDER_TABLES = ['orders', 'order_lines', 'order_status_history'];

    /** Views over mv_daily_sales: a materialized view cannot have policies, so they filter by the soda of the transaction themselves. */
    private const array BARRIER_VIEWS = ['v_daily_sales'];

    private SodaModel $soda;

    private SodaModel $otherSoda;

    /** Give two sodas one row in every table protected by row-level security. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->soda = SodaModel::factory()->create();
        $this->otherSoda = SodaModel::factory()->create();

        foreach ([$this->soda, $this->otherSoda] as $soda) {
            foreach (self::models() as [$model]) {
                $model::factory()->create(['soda_id' => $soda->id]);
            }
        }
    }

    /**
     * Every view with a soda_id column runs with the permissions of whoever queries it, so the policies of its tables
     * apply; the views over a materialized view, which has no policies, filter by the soda themselves behind a security barrier.
     */
    public function test_every_view_of_a_soda_runs_as_its_caller_or_filters_behind_a_security_barrier(): void
    {
        $views = $this->relationsWithASodaColumn('VIEW');

        $this->assertContains('v_hourly_sales', $views);
        $this->assertContains('v_daily_sales', $views);

        foreach ($views as $view) {
            $this->assertSame(
                in_array($view, self::BARRIER_VIEWS, true) ? 'security_barrier=true' : 'security_invoker=true',
                DB::scalar("select array_to_string(reloptions, ',') from pg_class where oid = ?::regclass", [$view]),
                "The view {$view} does not have the security option it needs.",
            );
        }
    }

    /** The tests run as the application role, the same one the requests use. */
    public function test_queries_run_as_the_application_role(): void
    {
        $this->assertSame(config('database.connections.pgsql.username'), DB::scalar('select current_user'));
    }

    /**
     * Every table with a soda_id column, except users, has row-level security enabled, forced and its isolation policy;
     * only the tables of an order have a second policy, the one that lets its customer read it.
     */
    public function test_every_table_of_a_soda_is_protected_by_its_policy(): void
    {
        $tables = $this->relationsWithASodaColumn('BASE TABLE');

        foreach (self::tables() as [$table]) {
            $this->assertContains($table, $tables);
        }

        foreach ($tables as $table) {
            $security = DB::selectOne(
                'select relrowsecurity, relforcerowsecurity from pg_class where oid = ?::regclass',
                [$table],
            );
            $policies = DB::select(
                "select policyname, cmd, qual, with_check from pg_policies where schemaname = 'public' and tablename = ?",
                [$table],
            );

            $this->assertTrue($security->relrowsecurity, "Row-level security is not enabled on {$table}.");
            $this->assertTrue($security->relforcerowsecurity, "Row-level security is not forced on {$table}.");
            $this->assertContainsEquals((object) [
                'policyname' => "{$table}_soda_isolation",
                'cmd' => 'ALL',
                'qual' => self::POLICY_EXPRESSION,
                'with_check' => self::POLICY_EXPRESSION,
            ], $policies, "The isolation policy of {$table} is missing or different.");
            $this->assertCount(in_array($table, self::ORDER_TABLES, true) ? 2 : 1, $policies, "{$table} has a policy nobody expects.");
        }
    }

    /** The tables of an order let the customer who placed it read them, and only read them: the policy covers SELECT and nothing else. */
    #[DataProvider('orderTables')]
    public function test_tables_of_an_order_let_their_customer_read_them(string $table, string $expression): void
    {
        $policy = DB::selectOne(
            "select cmd, qual, with_check from pg_policies where schemaname = 'public' and tablename = ? and policyname = ?",
            [$table, "{$table}_customer_access"],
        );

        $this->assertNotNull($policy, "The customer policy of {$table} is missing.");
        $this->assertSame('SELECT', $policy->cmd);
        $this->assertNull($policy->with_check);
        $this->assertSame($expression, preg_replace('/\s+/', ' ', (string) $policy->qual));
    }

    /**
     * Tables of an order with the expression that lets their customer read them.
     *
     * @return array<string, array{string, string}>
     */
    public static function orderTables(): array
    {
        $throughOrder = fn (string $table): string => '(EXISTS ( SELECT 1 FROM orders WHERE ((orders.id = '.$table.'.order_id) AND (orders.customer_id = (NULLIF(current_setting(\'app.customer_id\'::text, true), \'\'::text))::uuid))))';

        return [
            'orders' => ['orders', self::CUSTOMER_EXPRESSION],
            'order lines' => ['order_lines', $throughOrder('order_lines')],
            'order status history' => ['order_status_history', $throughOrder('order_status_history')],
        ];
    }

    /** Without a soda in the transaction no row is visible and no update or delete reaches one. */
    #[DataProvider('tables')]
    public function test_without_a_soda_no_row_is_visible_or_changed(string $table): void
    {
        $this->scopeQueriesTo(null);

        $this->assertSame(0, DB::table($table)->count());
        $this->assertSame(0, DB::table($table)->update(['updated_at' => now()]));
        $this->assertSame(0, DB::table($table)->delete());

        $this->scopeQueriesTo($this->soda->id);
        $this->assertSame(1, DB::table($table)->count());
    }

    /**
     * Unfiltered Eloquent queries return only the rows of the soda set for the transaction.
     *
     * @param  class-string<Model>  $model
     */
    #[DataProvider('models')]
    public function test_unfiltered_query_returns_only_the_rows_of_the_soda(string $model): void
    {
        foreach ([$this->soda, $this->otherSoda] as $soda) {
            $this->scopeQueriesTo($soda->id);

            $this->assertSame([$soda->id], $model::query()->pluck('soda_id')->all());
        }
    }

    /** Inserting a row for another soda fails with the row-level security error. */
    #[DataProvider('newRows')]
    public function test_inserting_a_row_for_another_soda_fails(string $table, array $row): void
    {
        $this->scopeQueriesTo($this->soda->id);

        $this->assertRowLevelSecurityViolation(
            fn () => DB::table($table)->insert([...$row, 'soda_id' => $this->otherSoda->id]),
        );
    }

    /** Moving a row to another soda fails with the same error and the row stays where it was. */
    #[DataProvider('tables')]
    public function test_moving_a_row_to_another_soda_fails(string $table): void
    {
        $this->scopeQueriesTo($this->soda->id);

        $this->assertRowLevelSecurityViolation(
            fn () => DB::table($table)->update(['soda_id' => $this->otherSoda->id]),
        );
        $this->assertSame([$this->soda->id], DB::table($table)->pluck('soda_id')->all());
    }

    /**
     * Tables protected by row-level security.
     *
     * @return array<string, array{string}>
     */
    public static function tables(): array
    {
        return array_map(fn (array $row): array => [$row[0]], self::newRows());
    }

    /**
     * Models of the protected tables.
     *
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        return [
            'categories' => [CategoryModel::class],
            'dishes' => [DishModel::class],
            'schedules' => [TimeSlotModel::class],
            'closures' => [ClosureModel::class],
            'promotions' => [PromotionModel::class],
        ];
    }

    /**
     * Protected tables paired with a valid new row, without its soda.
     *
     * @return array<string, array{string, array<string, int|string>}>
     */
    public static function newRows(): array
    {
        return [
            'categories' => ['categories', ['name' => 'Casados']],
            'dishes' => ['dishes', [
                'name' => 'Olla de carne',
                'price' => 3500,
                'preparation_minutes' => 20,
                'available_portions' => 10,
            ]],
            'schedules' => ['schedules', ['day_of_week' => 2, 'opens_at' => '08:00', 'closes_at' => '12:00']],
            'closures' => ['closures', ['closed_on' => '2026-12-25']],
            'promotions' => ['promotions', ['weekday' => 4, 'starts_at' => '14:00', 'ends_at' => '15:00', 'percentage' => 20]],
        ];
    }

    /** Run a write inside a savepoint and expect PostgreSQL to reject it by its row-level security policy. */
    private function assertRowLevelSecurityViolation(Closure $write): void
    {
        try {
            DB::transaction($write);
            $this->fail('A row crossed to another soda.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
            $this->assertStringContainsString('violates row-level security policy', $exception->getMessage());
        }
    }

    /**
     * Tables or views of the public schema with a soda_id column, except users.
     *
     * @return list<string>
     */
    private function relationsWithASodaColumn(string $type): array
    {
        return array_column(DB::select(
            "select c.table_name from information_schema.columns c
             join information_schema.tables t on t.table_schema = c.table_schema and t.table_name = c.table_name
             where c.table_schema = 'public' and c.column_name = 'soda_id' and c.table_name <> 'users' and t.table_type = ?",
            [$type],
        ), 'table_name');
    }
}
