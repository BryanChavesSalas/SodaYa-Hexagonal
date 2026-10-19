<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Src\Reporting\Sales\Application\Contracts\DailySalesSummary;

final readonly class MaintenanceDailySalesSummary implements DailySalesSummary
{
    public const string CONNECTION = 'pgsql_maintenance';

    public const string VIEW = 'mv_daily_sales';

    /**
     * Refresh the view with the maintenance role, which holds MAINTAIN on it, without locking out the readers,
     * and record the moment in the same transaction: the moment is written only if the refresh is.
     */
    public function refresh(DateTimeImmutable $now): void
    {
        DB::connection(self::CONNECTION)->transaction(function (ConnectionInterface $connection) use ($now): void {
            $connection->statement('REFRESH MATERIALIZED VIEW CONCURRENTLY '.self::VIEW);
            $connection->statement(
                'INSERT INTO materialized_view_refreshes (view_name, refreshed_at) VALUES (?, ?)
                 ON CONFLICT (view_name) DO UPDATE SET refreshed_at = EXCLUDED.refreshed_at',
                [self::VIEW, $now->format(DATE_ATOM)],
            );
        });
    }
}
