<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Console;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Src\Catalog\Dishes\Application\UseCases\ResetDailyPortions;

#[Signature('sodaya:reset-daily-portions')]
final class ResetDailyPortionsConsoleCommand extends Command
{
    /** Describe the command in Spanish for the command list. */
    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('catalog.portions_reset_description'));
    }

    /** Give every dish of every soda its base portions back. */
    public function handle(ResetDailyPortions $resetDailyPortions): int
    {
        $resetDailyPortions->execute();

        $this->components->info(__('catalog.daily_portions_reset'));

        return self::SUCCESS;
    }
}
