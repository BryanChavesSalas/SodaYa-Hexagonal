<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Sodas\Schedules\Domain\Contracts\ScheduleRepository;
use Src\Sodas\Schedules\Infrastructure\Persistence\Repositories\EloquentScheduleRepository;

final class SchedulesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ScheduleRepository::class => EloquentScheduleRepository::class,
    ];
}
