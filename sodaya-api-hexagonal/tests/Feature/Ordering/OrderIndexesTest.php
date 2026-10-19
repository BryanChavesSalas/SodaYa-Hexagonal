<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Facades\DB;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class OrderIndexesTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private const string INDEX = 'orders_soda_id_status_created_at_index';

    private SodaModel $soda;

    /** Load 10 000 orders: 8 000 of the soda over 80 days and 2 000 of another soda, each with one line. */
    protected function setUp(): void
    {
        parent::setUp();

        $customer = UserModel::factory()->create();
        $this->soda = SodaModel::factory()->create();
        $this->insertOrders($this->soda, $customer, 8_000);
        $this->insertOrders(SodaModel::factory()->create(), $customer, 2_000);
        $this->scopeQueriesTo($this->soda->id);
    }

    /** The hourly sales report reads the orders of one day through the index, not the whole table. */
    public function test_hourly_sales_report_uses_the_index(): void
    {
        $plan = $this->plan('SELECT * FROM fn_hourly_sales(?::uuid, ?::date, 1)', [$this->soda->id, '2026-09-15']);

        $this->assertContains(self::INDEX, $this->indexesIn($plan));
    }

    /** The kitchen list of pending orders, oldest first, comes from the index already in order. */
    public function test_kitchen_list_uses_the_index_without_sorting(): void
    {
        $query = OrderModel::query()
            ->where('soda_id', $this->soda->id)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(15);

        $plan = $this->plan($query->toSql(), $query->getBindings());

        $this->assertContains(self::INDEX, $this->indexesIn($plan));
        $this->assertNotContains('Sort', $this->nodeTypesIn($plan));
    }

    /**
     * Run the query with EXPLAIN ANALYZE and return its plan.
     *
     * @param  list<mixed>  $bindings
     * @return array<string, mixed>
     */
    private function plan(string $sql, array $bindings): array
    {
        $json = DB::scalar('EXPLAIN (ANALYZE, FORMAT JSON) '.$sql, $bindings);
        $explain = json_decode(is_string($json) ? $json : '[]', true, flags: JSON_THROW_ON_ERROR);

        return $explain[0]['Plan'];
    }

    /**
     * Indexes the plan reads, at any depth.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function indexesIn(array $node): array
    {
        return $this->collect($node, 'Index Name');
    }

    /**
     * Node types of the plan, at any depth.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function nodeTypesIn(array $node): array
    {
        return $this->collect($node, 'Node Type');
    }

    /**
     * Values of a key in a node and its children.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function collect(array $node, string $key): array
    {
        $values = isset($node[$key]) && is_string($node[$key]) ? [$node[$key]] : [];

        foreach (is_array($node['Plans'] ?? null) ? $node['Plans'] : [] as $child) {
            $values = [...$values, ...$this->collect($child, $key)];
        }

        return $values;
    }

    /** Insert orders of a soda in bulk, spread over 80 days from August 1st, 2026, with every status. */
    private function insertOrders(SodaModel $soda, UserModel $customer, int $count): void
    {
        $dish = DishModel::factory()->create(['soda_id' => $soda->id]);
        $this->scopeQueriesTo($soda->id);

        DB::insert(<<<'SQL'
            INSERT INTO orders (soda_id, customer_id, status, cancellation_origin, cancelled_at, payment_status, paid_at, pickup_date, pickup_number, created_at, updated_at)
            SELECT ?, ?, s.status,
                   CASE WHEN s.status = 'cancelled' THEN 'kitchen' END,
                   CASE WHEN s.status = 'cancelled' THEN s.at END,
                   CASE WHEN s.status = 'pending' THEN 'pending' ELSE 'approved' END,
                   CASE WHEN s.status = 'pending' THEN NULL ELSE s.at END,
                   (s.at AT TIME ZONE 'America/Costa_Rica')::date, s.number,
                   s.at, s.at
            FROM (
                SELECT (ARRAY['delivered', 'delivered', 'delivered', 'delivered', 'delivered', 'delivered',
                              'pending', 'confirmed', 'ready', 'cancelled'])[i % 10 + 1] AS status,
                       timestamptz '2026-08-01 07:00:00-06' + (i % 80) * interval '1 day' + (i % 600) * interval '1 minute' AS at,
                       i / 80 + 1 AS number
                FROM generate_series(1, ?) AS i
            ) AS s
            SQL, [$soda->id, $customer->id, $count]);

        DB::insert(<<<'SQL'
            INSERT INTO order_lines (order_id, soda_id, dish_id, dish_name, unit_price, quantity, preparation_minutes)
            SELECT o.id, o.soda_id, ?, 'Casado', 2500, 1, 15 FROM orders o WHERE o.soda_id = ?
            SQL, [$dish->id, $soda->id]);
    }
}
