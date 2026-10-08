<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Schedules;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ScheduleSchemaTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** An opening equal to or after the closing is rejected by the CHECK constraint. */
    #[DataProvider('invalidRanges')]
    public function test_database_rejects_opening_not_before_closing(string $opens, string $closes): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('schedules_opens_before_closes_check');

        $this->insertSlot($this->insertSoda(), 1, $opens, $closes);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidRanges(): array
    {
        return ['equal' => ['08:00', '08:00'], 'reversed' => ['18:00', '08:00']];
    }

    /** A day outside 1..7 is rejected. */
    public function test_database_rejects_a_day_out_of_range(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('schedules_day_of_week_check');

        $this->insertSlot($this->insertSoda(), 8, '08:00', '12:00');
    }

    /** Overlapping slots of the same soda and day are rejected by the EXCLUDE constraint. */
    public function test_database_rejects_overlapping_slots(): void
    {
        $soda = $this->insertSoda();
        $this->insertSlot($soda, 1, '08:00', '12:00');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('schedules_no_overlap_exclude');

        $this->insertSlot($soda, 1, '11:00', '13:00');
    }

    /** Closing is excluded, other days and other sodas may reuse the hours. */
    public function test_database_accepts_adjacent_and_isolated_slots(): void
    {
        $soda = $this->insertSoda();

        $this->insertSlot($soda, 1, '08:00', '12:00');
        $this->insertSlot($soda, 1, '12:00', '15:00');
        $this->insertSlot($soda, 2, '08:00', '12:00');
        $this->insertSlot($this->insertSoda(), 1, '08:00', '12:00');

        $this->assertDatabaseCount('schedules', 4);
    }

    /** The migration creates the extension and its table, and rolls back cleanly. */
    public function test_migration_rolls_back_the_table(): void
    {
        $schema = Schema::connection('pgsql_admin');

        $this->assertTrue($schema->hasTable('schedules'));
        $this->assertSame(1, DB::connection('pgsql_admin')->table('pg_extension')->where('extname', 'btree_gist')->count());

        $this->artisan('migrate:rollback', [
            '--database' => 'pgsql_admin',
            '--path' => 'database/migrations/2026_10_05_000100_create_schedules_table.php',
        ])->assertSuccessful();

        $this->assertFalse($schema->hasTable('schedules'));

        $this->artisan('migrate', ['--database' => 'pgsql_admin'])->assertSuccessful();

        $this->assertTrue($schema->hasTable('schedules'));
    }

    /** Insert a soda and return its identifier. */
    private function insertSoda(): string
    {
        return (string) DB::table('sodas')->insertGetId(['name' => 'Soda de prueba']);
    }

    /** Insert a slot straight into the table. */
    private function insertSlot(string $sodaId, int $day, string $opens, string $closes): void
    {
        DB::table('schedules')->insert([
            'soda_id' => $sodaId,
            'day_of_week' => $day,
            'opens_at' => $opens,
            'closes_at' => $closes,
        ]);
    }
}
