<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Application;

use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Application\UseCases\ListCustomerOrders;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Domain\ValueObjects\PickupNumber;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Ordering\InMemoryOrderRepository;
use Tests\Support\Ordering\Orders;

final class ListCustomerOrdersTest extends TestCase
{
    private const string OTHER_CUSTOMER_ID = '0192f0c4-0000-7000-8000-00000000c002';

    private const string OTHER_SODA_ID = '0192f0c4-0000-7000-8000-00000000b002';

    private InMemoryOrderRepository $orders;

    private ListCustomerOrders $listOrders;

    /** Store three orders of the customer, at two sodas and different moments, and one of another customer. */
    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository;
        $this->listOrders = new ListCustomerOrders($this->orders);

        $this->orders->save($this->orderAt('a001', '12:00', Orders::CUSTOMER_ID, Orders::SODA_ID));
        $this->orders->save($this->orderAt('a002', '12:30', Orders::CUSTOMER_ID, self::OTHER_SODA_ID));
        $this->orders->save($this->orderAt('a003', '12:15', Orders::CUSTOMER_ID, Orders::SODA_ID));
        $this->orders->save($this->orderAt('a009', '12:45', self::OTHER_CUSTOMER_ID, Orders::SODA_ID));
    }

    /** The page holds only the orders of the customer, at every soda, from the newest to the oldest. */
    public function test_lists_the_orders_of_the_customer_newest_first(): void
    {
        $page = $this->listOrders->execute(Orders::CUSTOMER_ID, new PageRequest);

        $this->assertSame(3, $page->total);
        $this->assertSame(['a002', 'a003', 'a001'], array_map(fn (Order $order): string => substr($order->id->value, -4), $page->items));
    }

    /** The page asked for is the one answered, with the total of the whole listing. */
    public function test_pages_the_listing(): void
    {
        $page = $this->listOrders->execute(Orders::CUSTOMER_ID, new PageRequest(2, 2));

        $this->assertSame(3, $page->total);
        $this->assertSame(2, $page->lastPage());
        $this->assertSame(['a001'], array_map(fn (Order $order): string => substr($order->id->value, -4), $page->items));
    }

    /** A customer without orders gets an empty page. */
    public function test_customer_without_orders_gets_an_empty_page(): void
    {
        $page = $this->listOrders->execute('0192f0c4-0000-7000-8000-00000000c003', new PageRequest);

        $this->assertSame([], $page->items);
        $this->assertSame(0, $page->total);
    }

    /** A pending order of a customer at a soda, placed at the given time. */
    private function orderAt(string $suffix, string $time, string $customerId, string $sodaId): Order
    {
        return Order::place(
            new OrderId('0192f0c4-0000-7000-8000-00000000'.$suffix),
            new SodaId($sodaId),
            new CustomerId($customerId),
            [Orders::line()],
            Orders::at($time),
            new PickupNumber(1),
        );
    }
}
