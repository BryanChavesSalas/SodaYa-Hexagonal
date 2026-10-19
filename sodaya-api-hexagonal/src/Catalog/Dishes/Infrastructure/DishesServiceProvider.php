<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Src\Catalog\Dishes\Application\Contracts\DailyPortions;
use Src\Catalog\Dishes\Domain\Contracts\DishRepository;
use Src\Catalog\Dishes\Infrastructure\Console\ResetDailyPortionsConsoleCommand;
use Src\Catalog\Dishes\Infrastructure\Persistence\Repositories\EloquentDishRepository;
use Src\Catalog\Dishes\Infrastructure\Persistence\StoredProcedureDailyPortions;

final class DishesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DishRepository::class => EloquentDishRepository::class,
        DailyPortions::class => StoredProcedureDailyPortions::class,
    ];

    /** Register the console command of the module and schedule it every morning, Costa Rica time. */
    public function boot(): void
    {
        $this->commands([ResetDailyPortionsConsoleCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(ResetDailyPortionsConsoleCommand::class)
                ->dailyAt((string) config('sodaya.daily_portions_reset_at'))
                ->timezone('America/Costa_Rica')
                ->withoutOverlapping(60);
        });
    }
}
