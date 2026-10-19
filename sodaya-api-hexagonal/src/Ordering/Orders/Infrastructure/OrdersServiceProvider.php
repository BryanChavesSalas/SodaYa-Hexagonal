<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Src\Ordering\Orders\Application\Contracts\CustomerNotices;
use Src\Ordering\Orders\Application\Contracts\ExpiredOrders;
use Src\Ordering\Orders\Application\Contracts\LoyaltyRewards;
use Src\Ordering\Orders\Application\Contracts\OrderableDishes;
use Src\Ordering\Orders\Application\Contracts\OrderDeadlines;
use Src\Ordering\Orders\Application\Contracts\PortionStock;
use Src\Ordering\Orders\Application\Contracts\RefundRequests;
use Src\Ordering\Orders\Application\Contracts\SodaSchedule;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Infrastructure\Catalog\DishPortionStock;
use Src\Ordering\Orders\Infrastructure\Catalog\EloquentOrderableDishes;
use Src\Ordering\Orders\Infrastructure\Console\ExpireOrdersConsoleCommand;
use Src\Ordering\Orders\Infrastructure\Loyalty\QueuedLoyaltyRewards;
use Src\Ordering\Orders\Infrastructure\Persistence\Queries\MaintenanceExpiredOrders;
use Src\Ordering\Orders\Infrastructure\Persistence\Repositories\EloquentOrderRepository;
use Src\Ordering\Orders\Infrastructure\Queue\QueuedCustomerNotices;
use Src\Ordering\Orders\Infrastructure\Queue\QueuedOrderDeadlines;
use Src\Ordering\Orders\Infrastructure\Queue\QueuedRefundRequests;
use Src\Ordering\Orders\Infrastructure\Sodas\OpeningHoursSodaSchedule;

final class OrdersServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        OrderRepository::class => EloquentOrderRepository::class,
        OrderableDishes::class => EloquentOrderableDishes::class,
        SodaSchedule::class => OpeningHoursSodaSchedule::class,
        PortionStock::class => DishPortionStock::class,
        RefundRequests::class => QueuedRefundRequests::class,
        CustomerNotices::class => QueuedCustomerNotices::class,
        OrderDeadlines::class => QueuedOrderDeadlines::class,
        ExpiredOrders::class => MaintenanceExpiredOrders::class,
        LoyaltyRewards::class => QueuedLoyaltyRewards::class,
    ];

    /** Register the console command of the module and run it every minute, as a safety net for the deadlines of the queue. */
    public function boot(): void
    {
        $this->commands([ExpireOrdersConsoleCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(ExpireOrdersConsoleCommand::class)
                ->everyMinute()
                ->withoutOverlapping(5);
        });
    }
}
