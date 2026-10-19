<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Ordering\Orders\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\DishId;
use Src\Ordering\Orders\Domain\ValueObjects\IdempotencyKey;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Ordering\Orders\Domain\ValueObjects\PaymentStatus;
use Src\Ordering\Orders\Domain\ValueObjects\PickupNumber;
use Src\Ordering\Orders\Domain\ValueObjects\Quantity;
use Src\Ordering\Orders\Infrastructure\Persistence\Repositories\EloquentOrderRepository;
use Src\Shared\Domain\ValueObjects\SodaId;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\Ordering\Orders;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class EloquentOrderRepositoryTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private EloquentOrderRepository $repository;

    private SodaId $sodaId;

    /** Give a soda two dishes and scope the queries to it. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentOrderRepository;
        $this->sodaId = new SodaId(SodaModel::factory()->create()->id);
        $this->scopeQueriesTo($this->sodaId->value);
    }

    /** A placed order is read back with its lines, its status and its moment. */
    public function test_saved_order_is_read_back_unchanged(): void
    {
        $order = $this->placedOrder();

        $this->repository->save($order);

        $found = $this->repository->find($order->id, $this->sodaId);

        $this->assertEquals($order, $found);
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(2, DB::table('order_lines')->count());
    }

    /** Saving again stores the payment, the new status, its moment and the origin of a cancellation; the lines stay. */
    public function test_saving_again_stores_the_status_changes(): void
    {
        $order = $this->placedOrder();
        $this->repository->save($order);

        $order->approvePayment(Orders::at('12:00'));
        $order->advanceTo(OrderStatus::Confirmed, Orders::at('12:01'));
        $order->cancel(CancellationOrigin::Kitchen, Orders::at('12:05'));
        $this->repository->save($order);

        $found = $this->repository->find($order->id, $this->sodaId);

        $this->assertSame(OrderStatus::Cancelled, $found?->status);
        $this->assertSame(CancellationOrigin::Kitchen, $found->cancellationOrigin);
        $this->assertSame(PaymentStatus::Approved, $found->paymentStatus);
        $this->assertEquals(Orders::at('12:00'), $found->paidAt);
        $this->assertEquals(Orders::at('12:01'), $found->changedAt(OrderStatus::Confirmed));
        $this->assertEquals(Orders::at('12:05'), $found->changedAt(OrderStatus::Cancelled));
        $this->assertSame(2, DB::table('order_lines')->count());
    }

    /** An order is invisible from another soda, with or without a lock. */
    public function test_order_of_another_soda_is_not_found(): void
    {
        $order = $this->placedOrder();
        $this->repository->save($order);

        $otherSodaId = new SodaId(SodaModel::factory()->create()->id);

        $this->assertNull($this->repository->find($order->id, $otherSodaId));
        $this->assertNull($this->repository->findForUpdate($order->id, $otherSodaId));
    }

    /** Finding for update reads the order with SELECT ... FOR UPDATE, which locks it until the transaction ends. */
    public function test_find_for_update_locks_the_row(): void
    {
        $order = $this->placedOrder();
        $this->repository->save($order);

        DB::enableQueryLog();
        $found = $this->repository->findForUpdate($order->id, $this->sodaId);
        $orderQuery = DB::getQueryLog()[0]['query'];

        $this->assertEquals($order, $found);
        $this->assertStringStartsWith('select * from "orders"', $orderQuery);
        $this->assertStringEndsWith('for update', $orderQuery);
    }

    /** An order placed with a key is found again by its customer and key, with the key and the fingerprint it was saved with. */
    public function test_order_is_found_by_its_idempotency_key(): void
    {
        $key = new IdempotencyKey('toque-1', str_repeat('a', 64));
        $order = $this->placedOrder($key);
        $this->repository->save($order);

        $found = $this->repository->findByIdempotencyKey($order->customerId, 'toque-1');

        $this->assertEquals($order, $found);
        $this->assertEquals($key, $found?->idempotencyKey);
        $this->assertNull($this->repository->findByIdempotencyKey($order->customerId, 'toque-2'));
        $this->assertNull($this->repository->findByIdempotencyKey(new CustomerId(Orders::CUSTOMER_ID), 'toque-1'));
    }

    /** A second order of the customer with the same key is refused by the database and nothing of it is stored. */
    public function test_second_order_with_the_same_key_is_refused(): void
    {
        $first = $this->placedOrder(new IdempotencyKey('toque-1', str_repeat('a', 64)));
        $this->repository->save($first);
        $second = Order::place($this->repository->nextId(), $this->sodaId, $first->customerId, $first->lines, Orders::at('12:01'), new PickupNumber(2), new IdempotencyKey('toque-1', str_repeat('b', 64)));

        try {
            $this->repository->save($second);
            $this->fail('The same key was stored twice.');
        } catch (IdempotencyKeyReusedException $exception) {
            $this->assertSame('conflicto', $exception->rule());
            $this->assertSame(1, DB::table('orders')->count());
            $this->assertSame(2, DB::table('order_lines')->count());
        }
    }

    /** Every new identity is a distinct time-ordered UUID. */
    public function test_next_id_returns_uuid_v7_values(): void
    {
        $this->assertTrue(Str::isUuid($this->repository->nextId()->value, 7));
        $this->assertNotEquals($this->repository->nextId(), $this->repository->nextId());
    }

    /** Place an order of a customer with two dishes of the soda, numbered 1, with the key of its request if there is one. */
    private function placedOrder(?IdempotencyKey $key = null): Order
    {
        $customer = UserModel::factory()->create();
        $casado = DishModel::factory()->create(['soda_id' => $this->sodaId->value, 'name' => 'Casado de pollo', 'price' => 2_800]);
        $fresco = DishModel::factory()->create(['soda_id' => $this->sodaId->value, 'name' => 'Fresco de cas', 'price' => 1_000]);
        $this->scopeQueriesTo($this->sodaId->value);

        return Order::place(
            $this->repository->nextId(),
            $this->sodaId,
            new CustomerId($customer->id),
            [
                new OrderLine(new DishId($casado->id), 'Casado de pollo', 2_800, new Quantity(2), 15),
                new OrderLine(new DishId($fresco->id), 'Fresco de cas', 1_000, new Quantity(1), 5),
            ],
            Orders::at('12:00'),
            new PickupNumber(1),
            $key,
        );
    }
}
