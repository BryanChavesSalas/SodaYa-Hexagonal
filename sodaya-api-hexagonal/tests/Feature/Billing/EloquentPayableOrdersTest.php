<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Infrastructure\Ordering\EloquentPayableOrders;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class EloquentPayableOrdersTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private EloquentPayableOrders $orders;

    private OrderModel $order;

    /** A pending order with two lines, ₡2800 × 2 and ₡1000 × 1, with the transaction scoped to its soda. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->orders = new EloquentPayableOrders;
        $this->order = OrderModel::factory()->create();
        OrderLineModel::factory()->for($this->order, 'order')->create(['unit_price' => 2_800, 'quantity' => 2]);
        OrderLineModel::factory()->for($this->order, 'order')->create(['unit_price' => 1_000, 'quantity' => 1]);
        $this->scopeQueriesTo($this->order->soda_id);
    }

    /** The order of the customer comes with its soda and the sum of its frozen lines, pending and unpaid. */
    public function test_finds_the_order_of_the_customer_with_the_amount_of_its_lines(): void
    {
        $payable = $this->orders->find(new OrderId($this->order->id), new CustomerId($this->order->customer_id));

        $this->assertNotNull($payable);
        $this->assertSame($this->order->id, $payable->id);
        $this->assertSame($this->order->soda_id, $payable->sodaId);
        $this->assertSame($this->order->customer_id, $payable->customerId);
        $this->assertSame(6_600, $payable->amount);
        $this->assertTrue($payable->pending);
        $this->assertFalse($payable->paid);
    }

    /** A paid order says so, and one the kitchen already confirmed is no longer pending. */
    public function test_tells_whether_the_order_is_paid_and_still_pending(): void
    {
        $paid = OrderModel::factory()->paid()->create(['soda_id' => $this->order->soda_id, 'customer_id' => $this->order->customer_id]);
        $confirmed = OrderModel::factory()->confirmed()->create(['soda_id' => $this->order->soda_id, 'customer_id' => $this->order->customer_id]);
        $customer = new CustomerId($this->order->customer_id);

        $this->assertTrue($this->orders->find(new OrderId($paid->id), $customer)?->paid);
        $this->assertTrue($this->orders->find(new OrderId($paid->id), $customer)?->pending);
        $this->assertFalse($this->orders->find(new OrderId($confirmed->id), $customer)?->pending);
    }

    /** An order of another customer is not found, like one that does not exist. */
    public function test_an_order_of_another_customer_is_not_found(): void
    {
        $other = new CustomerId(UserModel::factory()->create()->id);

        $this->assertNull($this->orders->find(new OrderId($this->order->id), $other));
    }

    /** From the transaction of another soda the order is not found: the policy of the table hides it. */
    public function test_an_order_is_not_found_from_another_soda(): void
    {
        $this->scopeQueriesTo(OrderModel::factory()->create()->soda_id);

        $this->assertNull($this->orders->find(new OrderId($this->order->id), new CustomerId($this->order->customer_id)));
    }
}
