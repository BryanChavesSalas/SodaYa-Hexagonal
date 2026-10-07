<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Application\UseCases;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Schedules\Application\DTOs\CreateScheduleCommand;
use Src\Sodas\Schedules\Domain\Contracts\ScheduleRepository;
use Src\Sodas\Schedules\Domain\Entities\Schedule;
use Src\Sodas\Schedules\Domain\ValueObjects\DayOfWeek;
use Src\Sodas\Schedules\Domain\ValueObjects\TimeOfDay;

final readonly class CreateScheduleUseCase
{
    /** Receive the schedule repository port. */
    public function __construct(private ScheduleRepository $schedules) {}

    /** Register a new slot for the soda. */
    public function execute(CreateScheduleCommand $command): Schedule
    {
        $schedule = new Schedule(
            $this->schedules->nextId(),
            new SodaId($command->sodaId),
            new DayOfWeek($command->dayOfWeek),
            new TimeOfDay($command->opensAt),
            new TimeOfDay($command->closesAt),
        );

        $this->schedules->save($schedule);

        return $schedule;
    }
}
