<?php

declare(strict_types=1);

namespace Tests\Feature\Sodas\Closures;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ClosureSchemaTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** The database generates a time-ordered identifier for a closure. */
    public function test_database_generates_the_identifier(): void
    {
        DB::table('closures')->insert(['soda_id' => $this->insertSoda(), 'closed_on' => '2026-12-25']);

        $this->assertTrue(Str::isUuid(DB::table('closures')->value('id'), 7));
    }

    /** A soda cannot close twice on the same date. */
    public function test_closure_is_unique_per_soda_and_date(): void
    {
        $sodaId = $this->insertSoda();
        $this->insertClosure($sodaId, '2026-12-25');

        $this->expectException(QueryException::class);

        $this->insertClosure($sodaId, '2026-12-25');
    }

    /** Different sodas may close on the same date. */
    public function test_different_sodas_may_close_on_the_same_date(): void
    {
        $this->insertClosure($this->insertSoda(), '2026-12-25');
        $this->insertClosure($this->insertSoda(), '2026-12-25');

        $this->assertSame(2, DB::table('closures')->where('closed_on', '2026-12-25')->count());
    }

    /** A closure must belong to an existing soda. */
    public function test_closure_requires_an_existing_soda(): void
    {
        $this->expectException(QueryException::class);

        $this->insertClosure((string) Str::uuid7(), '2026-12-25');
    }

    /** The reason cannot exceed its maximum length. */
    public function test_reason_is_limited_in_length(): void
    {
        $this->expectException(QueryException::class);

        $this->insertClosure($this->insertSoda(), '2026-12-25', str_repeat('a', 201));
    }

    /** Deleting a soda deletes its closures. */
    public function test_deleting_a_soda_deletes_its_closures(): void
    {
        $sodaId = $this->insertSoda();
        $this->insertClosure($sodaId, '2026-12-25');

        DB::table('sodas')->where('id', $sodaId)->delete();

        $this->assertSame(0, DB::table('closures')->count());
    }

    /** Insert a soda and return its identifier. */
    private function insertSoda(): string
    {
        $id = (string) Str::uuid7();

        DB::table('sodas')->insert(['id' => $id, 'name' => 'Soda de prueba']);

        return $id;
    }

    /** Insert a closure for the soda and return its identifier. */
    private function insertClosure(string $sodaId, string $closedOn, ?string $reason = null): string
    {
        $id = (string) Str::uuid7();

        DB::table('closures')->insert([
            'id' => $id,
            'soda_id' => $sodaId,
            'closed_on' => $closedOn,
            'reason' => $reason,
        ]);

        return $id;
    }
}
