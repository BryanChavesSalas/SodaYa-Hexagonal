<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure\Persistence\Queries;

use Illuminate\Support\Facades\DB;
use Src\Reporting\Sales\Application\Contracts\SalesReport;
use Src\Reporting\Sales\Application\DTOs\HourlySales;
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
}
