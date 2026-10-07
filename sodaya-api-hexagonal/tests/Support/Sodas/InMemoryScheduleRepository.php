<?php

declare(strict_types=1);

namespace Tests\Support\Sodas;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Schedules\Domain\Contracts\ScheduleRepository;
use Src\Sodas\Schedules\Domain\Entities\Schedule;
use Src\Sodas\Schedules\Domain\Exceptions\ScheduleOverlapException;
use Src\Sodas\Schedules\Domain\ValueObjects\ScheduleId;

final class InMemoryScheduleRepository implements ScheduleRepository
{
    /** @var array<string, Schedule> */
    private array $schedules = [];

    private int $sequence = 0;

    /** Generate a predictable identity. */
    public function nextId(): ScheduleId
    {
        return new ScheduleId(sprintf('0192f0c4-1000-7000-8000-%012d', ++$this->sequence));
    }

    /** Store the slot rejecting overlaps, as the database exclusion constraint does. */
    public function save(Schedule $schedule): void
    {
        foreach ($this->schedules as $stored) {
            if ($stored->overlaps($schedule)) {
                throw ScheduleOverlapException::create();
            }
        }

        $this->schedules[$schedule->id->value] = $schedule;
    }

    /**
     * List the slots of the soda ordered by day and opening time.
     *
     * @return list<Schedule>
     */
    public function allOf(SodaId $sodaId): array
    {
        $schedules = array_filter($this->schedules, fn (Schedule $schedule): bool => $schedule->sodaId->equals($sodaId));

        usort($schedules, fn (Schedule $a, Schedule $b): int => [$a->day->number, $a->opensAt->minutes] <=> [$b->day->number, $b->opensAt->minutes]);

        return $schedules;
    }
}
