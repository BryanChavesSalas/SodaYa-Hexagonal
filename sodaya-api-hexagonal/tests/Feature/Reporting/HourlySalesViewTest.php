<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use Illuminate\Support\Facades\DB;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class HourlySalesViewTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private OrderModel $order;

    /** Deliver one order of a soda at noon, Costa Rica time. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->order = OrderModel::factory()->delivered()->create(['created_at' => '2026-10-05 12:15:00']);
        OrderLineModel::factory()->for($this->order, 'order')->create(['unit_price' => 2500, 'quantity' => 2]);
    }

    /** The view shows one row per delivered order, with its Costa Rica hour and the sum of its lines. */
    public function test_view_has_one_row_per_delivered_order(): void
    {
        $this->scopeQueriesTo($this->order->soda_id);

        $row = DB::table('v_hourly_sales')->sole();

        $this->assertSame($this->order->id, $row->order_id);
        $this->assertSame(12, $row->sale_hour);
        $this->assertSame(5000, $row->total);
    }

    /** Without the soda of the transaction, neither the view nor the function show anything. */
    public function test_view_and_function_show_nothing_without_a_soda(): void
    {
        $this->scopeQueriesTo(null);

        $this->assertSame(0, DB::table('v_hourly_sales')->count());
        $this->assertSame([], DB::select('SELECT * FROM fn_hourly_sales(?::uuid, ?::date)', [$this->order->soda_id, '2026-10-05']));
    }

    /** The function cannot read another soda even if it is asked for its identifier. */
    public function test_function_cannot_read_another_soda(): void
    {
        $other = OrderModel::factory()->create();
        $this->scopeQueriesTo($other->soda_id);

        $this->assertSame([], DB::select('SELECT * FROM fn_hourly_sales(?::uuid, ?::date)', [$this->order->soda_id, '2026-10-05']));
    }
}
