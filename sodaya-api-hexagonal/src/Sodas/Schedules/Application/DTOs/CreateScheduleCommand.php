<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Application\DTOs;

final readonly class CreateScheduleCommand
{
    /** Carry the data needed to register a slot; times are `HH:MM`. */
    public function __construct(
        public string $sodaId,
        public int $dayOfWeek,
        public string $opensAt,
        public string $closesAt,
    ) {}
}
