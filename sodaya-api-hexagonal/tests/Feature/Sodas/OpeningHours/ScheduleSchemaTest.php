<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\OpeningHours;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ScheduleSchemaTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** The application role stores slots and the database generates their identifier. */
    public function test_application_role_stores_a_slot(): void
    {
        $this->insertSlot($this->insertSoda(), 1, '08:00', '12:00');

        $this->assertTrue(Str::isUuid(DB::table('schedules')->value('id'), 7));
    }

    /** Rows that break a check constraint are rejected by the database. */
    #[DataProvider('invalidRows')]
    public function test_database_rejects_invalid_rows(int $day, string $opensAt, string $closesAt): void
    {
        $sodaId = $this->insertSoda();

        try {
            $this->insertSlot($sodaId, $day, $opensAt, $closesAt);
            $this->fail('An invalid slot was stored.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
    }

    /**
     * Slots outside the allowed days or with inverted times.
     *
     * @return array<string, array{int, string, string}>
     */
    public static function invalidRows(): array
    {
        return [
            'day before monday' => [0, '08:00', '12:00'],
            'day after sunday' => [8, '08:00', '12:00'],
            'closing before opening' => [1, '12:00', '08:00'],
            'closing equal to opening' => [1, '08:00', '08:00'],
        ];
    }

    /** Two slots of the same soda and day cannot share a minute. */
    public function test_database_rejects_an_overlapping_slot(): void
    {
        $sodaId = $this->insertSoda();
        $this->insertSlot($sodaId, 1, '08:00', '12:00');

        try {
            $this->insertSlot($sodaId, 1, '11:59', '15:00');
            $this->fail('An overlapping slot was stored.');
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->getCode());
        }
    }

    /** A slot may start at the minute another one closes. */
    public function test_touching_slots_are_accepted(): void
    {
        $sodaId = $this->insertSoda();

        $this->insertSlot($sodaId, 1, '08:00', '12:00');
        $this->insertSlot($sodaId, 1, '12:00', '15:00');

        $this->assertSame(2, DB::table('schedules')->where('soda_id', $sodaId)->count());
    }

    /** The same hours are accepted on another day and for another soda. */
    public function test_same_hours_are_accepted_on_another_day_or_soda(): void
    {
        $sodaId = $this->insertSoda();

        $this->insertSlot($sodaId, 1, '08:00', '12:00');
        $this->insertSlot($sodaId, 2, '08:00', '12:00');
        $this->insertSlot($this->insertSoda(), 1, '08:00', '12:00');

        $this->assertSame(3, DB::table('schedules')->count());
    }

    /** Insert a soda and return its identifier. */
    private function insertSoda(): string
    {
        $id = (string) Str::uuid7();

        DB::table('sodas')->insert(['id' => $id, 'name' => 'Soda de prueba']);

        return $id;
    }

    /** Insert a slot for the soda straight into the table. */
    private function insertSlot(string $sodaId, int $day, string $opensAt, string $closesAt): void
    {
        DB::table('schedules')->insert([
            'soda_id' => $sodaId,
            'day_of_week' => $day,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
        ]);
    }
}
