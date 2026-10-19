<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Src\Ordering\Orders\Infrastructure\Persistence\CounterPickupNumbers;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Ordering\Orders;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class CounterPickupNumbersTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private CounterPickupNumbers $numbers;

    private SodaId $sodaId;

    /** Create a soda and scope the queries to it, as the request of an order does. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->numbers = new CounterPickupNumbers;
        $this->sodaId = new SodaId(SodaModel::factory()->create()->id);
        $this->scopeQueriesTo($this->sodaId->value);
    }

    /** The first order of the day is number 1 and each one after it takes the next number; the counter keeps the last. */
    public function test_numbers_of_a_day_are_consecutive_from_one(): void
    {
        $this->assertSame(1, $this->numbers->nextFor($this->sodaId, Orders::at('07:00'))->value);
        $this->assertSame(2, $this->numbers->nextFor($this->sodaId, Orders::at('12:00'))->value);
        $this->assertSame(3, $this->numbers->nextFor($this->sodaId, Orders::at('13:30'))->value);

        $this->assertSame(3, DB::table('pickup_counters')->where('pickup_date', '2026-10-05')->value('last_number'));
        $this->assertSame(1, DB::table('pickup_counters')->count());
    }

    /** The day of an order is the day of Costa Rica, not of UTC: a minute before midnight still counts, midnight starts again at 1. */
    public function test_days_change_at_midnight_in_costa_rica(): void
    {
        $this->numbers->nextFor($this->sodaId, Orders::at('23:30'));

        $this->assertSame(2, $this->numbers->nextFor($this->sodaId, new DateTimeImmutable('2026-10-06 05:59:00', new DateTimeZone('UTC')))->value);
        $this->assertSame(1, $this->numbers->nextFor($this->sodaId, new DateTimeImmutable('2026-10-06 06:00:00', new DateTimeZone('UTC')))->value);

        $this->assertSame(['2026-10-05', '2026-10-06'], DB::table('pickup_counters')->orderBy('pickup_date')->pluck('pickup_date')->all());
    }

    /** Each soda counts its own orders: another soda starts at 1 the same day and does not move the first counter. */
    public function test_two_sodas_do_not_share_a_counter(): void
    {
        $otherSodaId = new SodaId(SodaModel::factory()->create()->id);
        $this->numbers->nextFor($this->sodaId, Orders::at('12:00'));
        $this->numbers->nextFor($this->sodaId, Orders::at('12:01'));

        $this->scopeQueriesTo($otherSodaId->value);
        $this->assertSame(1, $this->numbers->nextFor($otherSodaId, Orders::at('12:02'))->value);

        $this->scopeQueriesTo($this->sodaId->value);
        $this->assertSame(3, $this->numbers->nextFor($this->sodaId, Orders::at('12:03'))->value);
    }
}
