<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use DateTimeImmutable;
use DateTimeZone;
use Src\Ordering\Orders\Application\Contracts\PickupNumbers;
use Src\Ordering\Orders\Domain\ValueObjects\PickupNumber;
use Src\Shared\Domain\ValueObjects\SodaId;

final class FakePickupNumbers implements PickupNumbers
{
    /** @var array<string, int> last number handed out, by soda and day */
    private array $counters = [];

    /** Count in memory per soda and day of Costa Rica, as the counter table does. */
    public function nextFor(SodaId $sodaId, DateTimeImmutable $moment): PickupNumber
    {
        $key = $sodaId->value.'@'.$moment->setTimezone(new DateTimeZone('America/Costa_Rica'))->format('Y-m-d');
        $this->counters[$key] = ($this->counters[$key] ?? 0) + 1;

        return new PickupNumber($this->counters[$key]);
    }
}
