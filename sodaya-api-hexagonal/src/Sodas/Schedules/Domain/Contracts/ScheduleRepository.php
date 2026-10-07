<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Schedules\Domain\Entities\Schedule;
use Src\Sodas\Schedules\Domain\Exceptions\ScheduleOverlapException;
use Src\Sodas\Schedules\Domain\ValueObjects\ScheduleId;

interface ScheduleRepository
{
    /** Generate the identity for a new slot. */
    public function nextId(): ScheduleId;

    /**
     * Persist a new slot.
     *
     * @throws ScheduleOverlapException
     */
    public function save(Schedule $schedule): void;

    /**
     * List every slot of a soda ordered by day and opening time.
     *
     * @return list<Schedule>
     */
    public function allOf(SodaId $sodaId): array;
}
