<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Src\Reporting\Sales\Application\UseCases\RefreshDailySales;

final class DailySalesSeeder extends Seeder
{
    /** Leave the daily sales summary up to date with the demo orders, as the scheduler would. */
    public function run(RefreshDailySales $refreshDailySales): void
    {
        $refreshDailySales->execute(now()->toDateTimeImmutable());
    }
}
