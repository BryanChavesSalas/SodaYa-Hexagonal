<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Application;

use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Application\UseCases\GetCustomerOrder;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotFoundException;
use Tests\Support\Ordering\InMemoryOrderRepository;
use Tests\Support\Ordering\Orders;

final class GetCustomerOrderTest extends TestCase
{
    private const string OTHER_CUSTOMER_ID = '0192f0c4-0000-7000-8000-00000000c002';

    private GetCustomerOrder $getOrder;

    /** Store a pending order of the customer. */
    protected function setUp(): void
    {
        $orders = new InMemoryOrderRepository;
        $orders->save(Orders::pending());
        $this->getOrder = new GetCustomerOrder($orders);
    }

    /** The customer reads their own order with its lines. */
    public function test_customer_reads_their_own_order(): void
    {
        $order = $this->getOrder->execute(Orders::ORDER_ID, Orders::CUSTOMER_ID);

        $this->assertSame(Orders::ORDER_ID, $order->id->value);
        $this->assertCount(2, $order->lines);
    }

    /** An order of another customer is not found, exactly like one that does not exist. */
    public function test_order_of_another_customer_is_not_found(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->getOrder->execute(Orders::ORDER_ID, self::OTHER_CUSTOMER_ID);
    }

    /** An unknown order is not found. */
    public function test_unknown_order_is_not_found(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->getOrder->execute('0192f0c4-0000-7000-8000-00000000a999', Orders::CUSTOMER_ID);
    }
}
