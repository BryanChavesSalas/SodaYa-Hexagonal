<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure\Persistence\Queries;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Reporting\Sales\Application\Contracts\SalesReport;
use Src\Reporting\Sales\Application\DTOs\DailySales;
use Src\Reporting\Sales\Application\DTOs\HourlySales;
use Src\Reporting\Sales\Infrastructure\Persistence\MaintenanceDailySalesSummary;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class PostgresSalesReport implements SalesReport
{
    /** Ask fn_hourly_sales for the hours of the date; row level security still limits it to the current soda. */
    public function hourlySales(SodaId $sodaId, string $date, int $minimumOrders): array
    {
        /** @var list<object{sale_hour: int, orders: int, total: int}> $rows */
        $rows = DB::select(
            'SELECT sale_hour, orders, total FROM fn_hourly_sales(?::uuid, ?::date, ?::integer)',
            [$sodaId->value, $date, $minimumOrders],
        );

        return array_map(
            fn (object $row): HourlySales => new HourlySales($row->sale_hour, $row->orders, $row->total),
            $rows,
        );
    }

    /** Read the days of the range from v_daily_sales, the precomputed summary that only shows the current soda; orders are never touched. */
    public function dailySales(SodaId $sodaId, string $from, string $to): array
    {
        /** @var list<object{day: string, orders: int, total: int}> $rows */
        $rows = DB::select(
            'SELECT day, orders, total FROM v_daily_sales WHERE soda_id = ? AND day BETWEEN ?::date AND ?::date ORDER BY day',
            [$sodaId->value, $from, $to],
        );

        return array_map(
            fn (object $row): DailySales => new DailySales($row->day, $row->orders, $row->total),
            $rows,
        );
    }

    /** Read the moment the maintenance role recorded with its last refresh of the summary. */
    public function dailySalesRefreshedAt(): ?DateTimeImmutable
    {
        $moment = DB::table('materialized_view_refreshes')
            ->where('view_name', MaintenanceDailySalesSummary::VIEW)
            ->value('refreshed_at');

        return is_string($moment) ? new DateTimeImmutable($moment) : null;
    }
}
