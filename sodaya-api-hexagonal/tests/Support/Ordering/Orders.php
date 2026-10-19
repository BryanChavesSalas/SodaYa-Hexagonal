<?php

declare(strict_types=1);

namespace Tests\Support\Ordering;

use DateTimeImmutable;
use DateTimeZone;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\DishId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Domain\ValueObjects\Quantity;
use Src\Shared\Domain\ValueObjects\SodaId;

final class Orders
{
    public const string ORDER_ID = '0192f0c4-0000-7000-8000-00000000a001';

    public const string SODA_ID = '0192f0c4-0000-7000-8000-00000000b001';

    public const string CUSTOMER_ID = '0192f0c4-0000-7000-8000-00000000c001';

    public const string CASADO = '0192f0c4-0000-7000-8000-00000000d001';

    public const string FRESCO = '0192f0c4-0000-7000-8000-00000000d002';

    /** A line of a dish with its frozen name and price. */
    public static function line(string $dishId = self::CASADO, int $quantity = 1, int $price = 2_800, string $name = 'Casado de pollo'): OrderLine
    {
        return new OrderLine(new DishId($dishId), $name, $price, new Quantity($quantity));
    }

    /** A pending order with two casados and a fresco, placed at 12:00 in Costa Rica. */
    public static function pending(string $orderId = self::ORDER_ID, string $sodaId = self::SODA_ID): Order
    {
        return Order::place(
            new OrderId($orderId),
            new SodaId($sodaId),
            new CustomerId(self::CUSTOMER_ID),
            [self::line(quantity: 2), self::line(self::FRESCO, 1, 1_000, 'Fresco de cas')],
            self::at('12:00'),
        );
    }

    /** The same pending order with its payment approved at 12:00, ready for the kitchen to confirm it. */
    public static function paid(string $orderId = self::ORDER_ID, string $sodaId = self::SODA_ID): Order
    {
        $order = self::pending($orderId, $sodaId);
        $order->approvePayment(self::at('12:00'));

        return $order;
    }

    /** A moment of 2026-10-05 in Costa Rica. */
    public static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("2026-10-05 {$time}", new DateTimeZone('America/Costa_Rica'));
    }
}
