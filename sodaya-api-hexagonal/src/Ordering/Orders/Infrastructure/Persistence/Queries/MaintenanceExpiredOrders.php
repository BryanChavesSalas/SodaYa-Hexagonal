<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Persistence\Queries;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Src\Ordering\Orders\Application\Contracts\ExpiredOrders;
use Src\Ordering\Orders\Application\DTOs\OrderReference;
use Src\Ordering\Orders\Domain\Entities\Order;

final readonly class MaintenanceExpiredOrders implements ExpiredOrders
{
    /** Read the expired orders with the maintenance role, which sees every soda; it only reads, it never cancels. */
    public function dueAt(DateTimeImmutable $now, int $limit): array
    {
        /** @var list<object{id: string, soda_id: string}> $rows */
        $rows = DB::connection('pgsql_maintenance')->select(
            "select id, soda_id
               from orders
              where status = 'pending'
                and ((payment_status = 'pending' and created_at <= ?) or (payment_status = 'approved' and paid_at <= ?))
              order by created_at
              limit ?",
            [
                $now->modify('-'.Order::MINUTES_TO_PAY.' minutes')->format(DATE_ATOM),
                $now->modify('-'.Order::MINUTES_TO_CONFIRM.' minutes')->format(DATE_ATOM),
                $limit,
            ],
        );

        return array_map(fn (object $row): OrderReference => new OrderReference($row->id, $row->soda_id), $rows);
    }
}
