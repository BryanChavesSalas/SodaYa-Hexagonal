<?php

declare(strict_types=1);

namespace Src\Ordering\Orders\Infrastructure\Catalog;

use Illuminate\Support\Facades\DB;
use Src\Catalog\Dishes\Domain\ValueObjects\Portions;
use Src\Ordering\Orders\Application\Contracts\PortionStock;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Shared\Domain\ValueObjects\SodaId;

final readonly class DishPortionStock implements PortionStock
{
    /**
     * Ask fn_reserve_portions to take the portions of every line in one statement.
     *
     * @param  list<OrderLine>  $lines
     */
    public function reserve(SodaId $sodaId, array $lines): bool
    {
        return (bool) DB::scalar('select fn_reserve_portions(?::uuid, ?::uuid[], ?::integer[])', [
            $sodaId->value,
            '{'.implode(',', array_map(fn (OrderLine $line): string => $line->dishId->value, $lines)).'}',
            '{'.implode(',', array_map(fn (OrderLine $line): int => $line->quantity->value, $lines)).'}',
        ]);
    }

    /**
     * Add the portions back to each dish, in the order of their identifiers, never above the maximum of the catalog.
     *
     * @param  list<OrderLine>  $lines
     */
    public function release(SodaId $sodaId, array $lines): void
    {
        usort($lines, fn (OrderLine $a, OrderLine $b): int => strcmp($a->dishId->value, $b->dishId->value));

        foreach ($lines as $line) {
            DB::update(
                'update dishes set available_portions = least(available_portions + ?, ?), updated_at = now() where id = ? and soda_id = ?',
                [$line->quantity->value, Portions::MAX, $line->dishId->value, $sodaId->value],
            );
        }
    }
}
