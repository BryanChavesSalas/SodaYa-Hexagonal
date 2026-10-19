<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Src\Ordering\Orders\Application\Contracts\PickupNumbers;
use Src\Ordering\Orders\Domain\ValueObjects\PickupNumber;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class CounterPickupNumbers implements PickupNumbers
{
    /**
     * Advance the counter row of the soda and day in one statement, creating it at 1 the first time:
     * the row lock of the UPDATE orders two orders of the same instant, so they never share a number.
     */
    public function nextFor(SodaId $sodaId, DateTimeImmutable $moment): PickupNumber
    {
        $day = $moment->setTimezone(new DateTimeZone('America/Costa_Rica'))->format('Y-m-d');

        $number = DB::scalar(<<<'SQL'
            INSERT INTO pickup_counters (soda_id, pickup_date, last_number)
            VALUES (?, ?, 1)
            ON CONFLICT (soda_id, pickup_date)
            DO UPDATE SET last_number = pickup_counters.last_number + 1
            RETURNING last_number
            SQL, [$sodaId->value, $day]);

        return new PickupNumber((int) $number);
    }
}
