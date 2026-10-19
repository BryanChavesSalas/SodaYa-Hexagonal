<?php

declare(strict_types=1);

namespace Src\Catalog\Dishes\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Src\Catalog\Dishes\Application\Contracts\DailyPortions;

final readonly class StoredProcedureDailyPortions implements DailyPortions
{
    public const string CONNECTION = 'pgsql_maintenance';

    /** Call the procedure with the maintenance role, outside any transaction: the procedure commits soda by soda. */
    public function resetAll(): void
    {
        DB::connection(self::CONNECTION)->statement('CALL sp_reset_daily_portions()');
    }
}
