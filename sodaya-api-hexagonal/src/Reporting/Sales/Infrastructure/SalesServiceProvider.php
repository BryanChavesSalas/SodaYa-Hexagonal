<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Src\Reporting\Sales\Application\Contracts\DailySalesSummary;
use Src\Reporting\Sales\Application\Contracts\SalesReport;
use Src\Reporting\Sales\Infrastructure\Console\RefreshDailySalesConsoleCommand;
use Src\Reporting\Sales\Infrastructure\Persistence\MaintenanceDailySalesSummary;
use Src\Reporting\Sales\Infrastructure\Persistence\Queries\PostgresSalesReport;

final class SalesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SalesReport::class => PostgresSalesReport::class,
        DailySalesSummary::class => MaintenanceDailySalesSummary::class,
    ];

    /** Register the console command of the module and refresh the daily summary every quarter of an hour, Costa Rica time. */
    public function boot(): void
    {
        $this->commands([RefreshDailySalesConsoleCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(RefreshDailySalesConsoleCommand::class)
                ->everyFifteenMinutes()
                ->timezone('America/Costa_Rica')
                ->withoutOverlapping(15);
        });
    }
}
