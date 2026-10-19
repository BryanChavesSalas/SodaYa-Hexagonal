<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog\Promotions;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class PromotionSchemaTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    /** The application role stores happy hours and the database generates their identifier. */
    public function test_application_role_stores_a_promotion(): void
    {
        $this->insertPromotion($this->insertSoda(), 3, '14:00', '15:00', 20);

        $this->assertTrue(Str::isUuid(DB::table('promotions')->value('id'), 7));
    }

    /** Rows that break a check constraint are rejected by the database. */
    #[DataProvider('invalidRows')]
    public function test_database_rejects_invalid_rows(int $weekday, string $startsAt, string $endsAt, int $percentage): void
    {
        $sodaId = $this->insertSoda();

        try {
            $this->insertPromotion($sodaId, $weekday, $startsAt, $endsAt, $percentage);
            $this->fail('An invalid happy hour was stored.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
    }

    /**
     * Happy hours outside the allowed days, with inverted times or with a discount out of range.
     *
     * @return array<string, array{int, string, string, int}>
     */
    public static function invalidRows(): array
    {
        return [
            'day before monday' => [0, '14:00', '15:00', 20],
            'day after sunday' => [8, '14:00', '15:00', 20],
            'end before start' => [3, '15:00', '14:00', 20],
            'end equal to start' => [3, '14:00', '14:00', 20],
            'no discount' => [3, '14:00', '15:00', 0],
            'discount of 95 percent' => [3, '14:00', '15:00', 95],
        ];
    }

    /** Two happy hours of the same soda and day cannot share a minute: the exclusion constraint answers 23P01. */
    public function test_database_rejects_an_overlapping_promotion(): void
    {
        $sodaId = $this->insertSoda();
        $this->insertPromotion($sodaId, 3, '14:00', '15:00', 20);

        try {
            $this->insertPromotion($sodaId, 3, '14:30', '16:00', 10);
            $this->fail('An overlapping happy hour was stored.');
        } catch (QueryException $exception) {
            $this->assertSame('23P01', $exception->getCode());
            $this->assertStringContainsString('promotions_no_overlap_excl', $exception->getMessage());
        }
    }

    /** A happy hour may start at the minute another one ends: the end is not part of the range. */
    public function test_touching_promotions_are_accepted(): void
    {
        $sodaId = $this->insertSoda();

        $this->insertPromotion($sodaId, 3, '14:00', '15:00', 20);
        $this->insertPromotion($sodaId, 3, '15:00', '16:00', 10);

        $this->assertSame(2, DB::table('promotions')->where('soda_id', $sodaId)->count());
    }

    /** The same window is accepted on another day and for another soda. */
    public function test_same_window_is_accepted_on_another_day_or_soda(): void
    {
        $sodaId = $this->insertSoda();

        $this->insertPromotion($sodaId, 3, '14:00', '15:00', 20);
        $this->insertPromotion($sodaId, 4, '14:00', '15:00', 20);
        $this->insertPromotion($this->insertSoda(), 3, '14:00', '15:00', 20);

        $this->assertSame(1, DB::table('promotions')->count());

        $this->scopeQueriesTo($sodaId);
        $this->assertSame(2, DB::table('promotions')->count());
    }

    /** Insert a soda and return its identifier. */
    private function insertSoda(): string
    {
        $id = (string) Str::uuid7();

        DB::table('sodas')->insert(['id' => $id, 'name' => 'Soda de prueba']);

        return $id;
    }

    /** Insert a happy hour for the soda straight into the table, under that soda. */
    private function insertPromotion(string $sodaId, int $weekday, string $startsAt, string $endsAt, int $percentage): void
    {
        $this->scopeQueriesTo($sodaId);

        DB::table('promotions')->insert([
            'soda_id' => $sodaId,
            'weekday' => $weekday,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'percentage' => $percentage,
        ]);
    }
}
