<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Application\UseCases;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Schedules\Domain\Contracts\ScheduleRepository;
use Src\Sodas\Schedules\Domain\Entities\Schedule;

final readonly class ListSchedulesUseCase
{
    /** Receive the schedule repository port. */
    public function __construct(private ScheduleRepository $schedules) {}

    /**
     * List the slots of the soda ordered by day and opening time.
     *
     * @return list<Schedule>
     */
    public function execute(string $sodaId): array
    {
        return $this->schedules->allOf(new SodaId($sodaId));
    }
}
