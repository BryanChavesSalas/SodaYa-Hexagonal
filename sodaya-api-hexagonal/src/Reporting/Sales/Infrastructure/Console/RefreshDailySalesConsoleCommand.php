<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Src\Reporting\Sales\Application\UseCases\RefreshDailySales;

#[Signature('sodaya:refresh-daily-sales')]
final class RefreshDailySalesConsoleCommand extends Command
{
    /** Describe the command in Spanish for the command list. */
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('reporting.refresh_daily_sales_description'));
    }

    /** Bring the daily sales summary of every soda up to date. */
    public function handle(RefreshDailySales $refreshDailySales): int
    {
        $refreshDailySales->execute(now()->toDateTimeImmutable());

        $this->components->info(__('reporting.daily_sales_refreshed'));

        return self::SUCCESS;
    }
}
