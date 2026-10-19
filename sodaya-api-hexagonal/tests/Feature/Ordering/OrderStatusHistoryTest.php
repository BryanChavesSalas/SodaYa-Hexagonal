<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Ordering\Orders\Application\DTOs\CancelOrderCommand;
use Src\Ordering\Orders\Application\UseCases\CancelOrder;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class OrderStatusHistoryTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private OrderModel $order;

    /** Create a pending order and scope the queries to its soda. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->order = OrderModel::factory()->create();
        OrderLineModel::factory()->for($this->order, 'order')->create();
        $this->scopeQueriesTo($this->order->soda_id);
    }

    /** A change made with plain SQL, outside the application, still leaves its row in the history. */
    public function test_status_change_by_sql_is_recorded(): void
    {
        DB::update("update orders set status = 'confirmed', confirmed_at = now() where id = ?", [$this->order->id]);

        $row = DB::table('order_status_history')->sole();
        $this->assertSame($this->order->id, $row->order_id);
        $this->assertSame($this->order->soda_id, $row->soda_id);
        $this->assertSame('pending', $row->from_status);
        $this->assertSame('confirmed', $row->to_status);
        $this->assertNotNull($row->changed_at);
    }

    /** Each change adds one row, in order; an update that keeps the status adds none. */
    public function test_each_change_adds_one_row(): void
    {
        DB::update("update orders set status = 'confirmed', confirmed_at = now() where id = ?", [$this->order->id]);
        DB::update('update orders set updated_at = now() where id = ?', [$this->order->id]);
        DB::update("update orders set status = 'ready', ready_at = now() where id = ?", [$this->order->id]);

        $this->assertSame(
            [['pending', 'confirmed'], ['confirmed', 'ready']],
            DB::table('order_status_history')->orderBy('id')->get()->map(fn (object $row): array => [$row->from_status, $row->to_status])->all(),
        );
    }

    /** A change made by the application is recorded the same way. */
    public function test_cancellation_by_the_application_is_recorded(): void
    {
        $this->app->make(CancelOrder::class)->execute(
            new CancelOrderCommand($this->order->id, $this->order->soda_id, 'kitchen'),
            now()->toDateTimeImmutable(),
        );

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $this->order->id,
            'from_status' => 'pending',
            'to_status' => 'cancelled',
        ]);
    }

    /** The application role can neither change nor delete the history. */
    public function test_application_role_cannot_alter_the_history(): void
    {
        DB::update("update orders set status = 'confirmed', confirmed_at = now() where id = ?", [$this->order->id]);

        foreach ([
            fn () => DB::table('order_status_history')->update(['to_status' => 'delivered']),
            fn () => DB::table('order_status_history')->delete(),
        ] as $change) {
            try {
                DB::transaction($change);
                $this->fail('The application role altered the history.');
            } catch (QueryException $exception) {
                $this->assertSame('42501', $exception->getCode());
            }
        }

        $this->assertSame(1, DB::table('order_status_history')->count());
    }

    /** The history of another soda is not visible. */
    public function test_history_of_another_soda_is_not_visible(): void
    {
        DB::update("update orders set status = 'confirmed', confirmed_at = now() where id = ?", [$this->order->id]);

        $this->scopeQueriesTo(OrderModel::factory()->create()->soda_id);

        $this->assertSame(0, DB::table('order_status_history')->count());
    }
}
