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

    /** The tests run as the application role, the same one the requests use. */
    public function test_queries_run_as_the_application_role(): void
    {
        $this->assertSame(config('database.connections.pgsql.username'), DB::scalar('select current_user'));
    }

    /** Every table with a soda_id column, except users, has row-level security enabled, forced and one isolation policy. */
    public function test_every_table_of_a_soda_is_protected_by_its_policy(): void
    {
        $tables = array_column(DB::select(
            "select table_name from information_schema.columns
             where table_schema = 'public' and column_name = 'soda_id' and table_name <> 'users'",
        ), 'table_name');

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
            $this->assertEquals([(object) [
                'policyname' => "{$table}_soda_isolation",
                'cmd' => 'ALL',
                'qual' => self::POLICY_EXPRESSION,
                'with_check' => self::POLICY_EXPRESSION,
            ]], $policies, "The isolation policy of {$table} is missing or different.");
        }
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
}
