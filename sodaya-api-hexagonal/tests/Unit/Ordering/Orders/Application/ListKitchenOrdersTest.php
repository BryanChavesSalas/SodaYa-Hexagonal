<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Application;

use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Application\UseCases\ListKitchenOrders;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Ordering\Orders\Domain\ValueObjects\PickupNumber;
use Src\Shared\Domain\ValueObjects\PageRequest;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Ordering\InMemoryOrderRepository;
use Tests\Support\Ordering\Orders;

final class ListKitchenOrdersTest extends TestCase
{
    private const string OTHER_SODA_ID = '0192f0c4-0000-7000-8000-00000000b002';

    private ListKitchenOrders $listOrders;

    /** Store, at the soda, a pending order of 12:10, a confirmed one of 12:00 and a cancelled one of 12:05; and a pending one at another soda. */
    protected function setUp(): void
    {
        $orders = new InMemoryOrderRepository;
        $this->listOrders = new ListKitchenOrders($orders);

        $orders->save($this->orderAt('a001', '12:10', Orders::SODA_ID));

        $confirmed = $this->orderAt('a002', '12:00', Orders::SODA_ID);
        $confirmed->approvePayment(Orders::at('12:01'));
        $confirmed->advanceTo(OrderStatus::Confirmed, Orders::at('12:02'));
        $orders->save($confirmed);

        $cancelled = $this->orderAt('a003', '12:05', Orders::SODA_ID);
        $cancelled->cancel(CancellationOrigin::Kitchen, Orders::at('12:06'));
        $orders->save($cancelled);

        $orders->save($this->orderAt('a009', '11:00', self::OTHER_SODA_ID));
    }

    /** Without a status, the page holds every order of the soda from the oldest to the newest, and none of another soda. */
    public function test_lists_every_order_of_the_soda_oldest_first(): void
    {
        $page = $this->listOrders->execute(Orders::SODA_ID, null, new PageRequest);

        $this->assertSame(3, $page->total);
        $this->assertSame(['a002', 'a003', 'a001'], $this->suffixesOf($page->items));
    }

    /** With a status, only the orders in that status are listed. */
    public function test_filters_by_status(): void
    {
        $this->assertSame(['a001'], $this->suffixesOf($this->listOrders->execute(Orders::SODA_ID, 'pending', new PageRequest)->items));
        $this->assertSame(['a002'], $this->suffixesOf($this->listOrders->execute(Orders::SODA_ID, 'confirmed', new PageRequest)->items));
        $this->assertSame([], $this->listOrders->execute(Orders::SODA_ID, 'ready', new PageRequest)->items);
    }

    /** The page asked for is the one answered, with the total of the whole listing. */
    public function test_pages_the_listing(): void
    {
        $page = $this->listOrders->execute(Orders::SODA_ID, null, new PageRequest(2, 2));

        $this->assertSame(3, $page->total);
        $this->assertSame(['a001'], $this->suffixesOf($page->items));
    }

    /** A pending order at the soda placed at the given time. */
    private function orderAt(string $suffix, string $time, string $sodaId): Order
    {
        return Order::place(
            new OrderId('0192f0c4-0000-7000-8000-00000000'.$suffix),
            new SodaId($sodaId),
            new CustomerId(Orders::CUSTOMER_ID),
            [Orders::line()],
            Orders::at($time),
            new PickupNumber(1),
        );
    }

    /**
     * Last four characters of the identifier of each order.
     *
     * @param  list<Order>  $orders
     * @return list<string>
     */
    private function suffixesOf(array $orders): array
    {
        return array_map(fn (Order $order): string => substr($order->id->value, -4), $orders);
    }
}
