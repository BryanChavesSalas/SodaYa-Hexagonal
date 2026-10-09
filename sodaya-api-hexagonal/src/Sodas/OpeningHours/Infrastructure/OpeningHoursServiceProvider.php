<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Sodas\OpeningHours\Domain\Contracts\TimeSlotRepository;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Repositories\EloquentTimeSlotRepository;

final class OpeningHoursServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        TimeSlotRepository::class => EloquentTimeSlotRepository::class,
    ];
}
