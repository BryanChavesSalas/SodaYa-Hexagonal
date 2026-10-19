<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Src\Ordering\Orders\Application\Contracts\CustomerNotices;
use Src\Ordering\Orders\Application\Contracts\ExpiredOrders;
use Src\Ordering\Orders\Application\Contracts\LoyaltyRewards;
use Src\Ordering\Orders\Application\Contracts\NoticeChannel;
use Src\Ordering\Orders\Application\Contracts\OrderableDishes;
use Src\Ordering\Orders\Application\Contracts\OrderDeadlines;
use Src\Ordering\Orders\Application\Contracts\PickupNumbers;
use Src\Ordering\Orders\Application\Contracts\PortionStock;
use Src\Ordering\Orders\Application\Contracts\Recipients;
use Src\Ordering\Orders\Application\Contracts\RefundRequests;
use Src\Ordering\Orders\Application\Contracts\SodaSchedule;
use Src\Ordering\Orders\Domain\Contracts\OrderRepository;
use Src\Ordering\Orders\Infrastructure\Catalog\DishPortionStock;
use Src\Ordering\Orders\Infrastructure\Catalog\EloquentOrderableDishes;
use Src\Ordering\Orders\Infrastructure\Console\ExpireOrdersConsoleCommand;
use Src\Ordering\Orders\Infrastructure\Identity\IdentityRecipients;
use Src\Ordering\Orders\Infrastructure\Loyalty\QueuedLoyaltyRewards;
use Src\Ordering\Orders\Infrastructure\Notices\LogNoticeChannel;
use Src\Ordering\Orders\Infrastructure\Notices\MailNoticeChannel;
use Src\Ordering\Orders\Infrastructure\Persistence\CounterPickupNumbers;
use Src\Ordering\Orders\Infrastructure\Persistence\Queries\EloquentSodaOfOrder;
use Src\Ordering\Orders\Infrastructure\Persistence\Queries\MaintenanceExpiredOrders;
use Src\Ordering\Orders\Infrastructure\Persistence\Repositories\EloquentOrderRepository;
use Src\Ordering\Orders\Infrastructure\Queue\QueuedCustomerNotices;
use Src\Ordering\Orders\Infrastructure\Queue\QueuedOrderDeadlines;
use Src\Ordering\Orders\Infrastructure\Queue\QueuedRefundRequests;
use Src\Ordering\Orders\Infrastructure\Sodas\OpeningHoursSodaSchedule;
use Src\Shared\Infrastructure\Tenancy\SodaOfOrder;
use UnhandledMatchError;

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
        PickupNumbers::class => CounterPickupNumbers::class,
        RefundRequests::class => QueuedRefundRequests::class,
        CustomerNotices::class => QueuedCustomerNotices::class,
        OrderDeadlines::class => QueuedOrderDeadlines::class,
        ExpiredOrders::class => MaintenanceExpiredOrders::class,
        LoyaltyRewards::class => QueuedLoyaltyRewards::class,
        SodaOfOrder::class => EloquentSodaOfOrder::class,
        Recipients::class => IdentityRecipients::class,
    ];

    /** Choose the channel of the customer notices from the configuration: the only place that knows which channels exist. */
    public function register(): void
    {
        $this->app->bind(NoticeChannel::class, fn (Application $app): NoticeChannel => match ($channel = config()->string('sodaya.notice_channel')) {
            'mail' => $app->make(MailNoticeChannel::class),
            'log' => $app->make(LogNoticeChannel::class),
            default => throw new UnhandledMatchError("Unknown notice channel: {$channel}."),
        });
    }

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
