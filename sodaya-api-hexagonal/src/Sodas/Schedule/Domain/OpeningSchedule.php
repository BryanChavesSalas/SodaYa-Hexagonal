<?php

declare(strict_types=1);

namespace Src\Sodas\Schedule\Domain;

use DateTimeImmutable;
use DateTimeZone;

final readonly class OpeningSchedule
{
    public const TIMEZONE = 'America/Costa_Rica';

    /**
     * @param  array<int, list<TimeSlot>>  $slotsByDay  1 = Monday ... 7 = Sunday
     * @param  list<ExceptionalClosure>  $closures
     */
    public function __construct(private array $slotsByDay, private array $closures = []) {}

    /** Tell whether the soda is open at the given moment (the clock is a parameter). */
    public function isOpenAt(DateTimeImmutable $now): bool
    {
        $local = $now->setTimezone(new DateTimeZone(self::TIMEZONE));
        $local = $local->setTime((int) $local->format('G'), (int) $local->format('i'));

        foreach ($this->closures as $closure) {
            if ($closure->covers($local)) {
                return false;
            }
        }

        $minuteOfDay = (int) $local->format('G') * 60 + (int) $local->format('i');

        foreach ($this->slotsByDay[(int) $local->format('N')] ?? [] as $slot) {
            if ($slot->covers($minuteOfDay)) {
                return true;
            }
        }

        return false;
    }
}
