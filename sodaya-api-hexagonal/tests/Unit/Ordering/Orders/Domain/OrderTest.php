<?php

declare(strict_types=1);

namespace Tests\Unit\Ordering\Orders\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\OrderEmptyException;
use Src\Ordering\Orders\Domain\Exceptions\OrderNotPaidException;
use Src\Ordering\Orders\Domain\Exceptions\QuantityOutOfRangeException;
use Src\Ordering\Orders\Domain\Exceptions\TransitionNotAllowedException;
use Src\Ordering\Orders\Domain\ValueObjects\CancellationOrigin;
use Src\Ordering\Orders\Domain\ValueObjects\CustomerId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderId;
use Src\Ordering\Orders\Domain\ValueObjects\OrderStatus;
use Src\Ordering\Orders\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Ordering\Orders;
use Throwable;

final class OrderTest extends TestCase
{
    /** A new order starts pending, keeps its lines and adds them up. */
    public function test_new_order_is_pending_and_totals_its_lines(): void
    {
        $order = Orders::pending();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertCount(2, $order->lines);
        $this->assertSame(5_600, $order->lines[0]->subtotal());
        $this->assertSame(6_600, $order->total());
        $this->assertEquals(Orders::at('12:00'), $order->placedAt);
        $this->assertNull($order->cancellationOrigin);
    }

    /** An order needs between 1 and 20 lines: none is an empty order, more than 20 is invalid data. */
    #[DataProvider('lineCounts')]
    public function test_number_of_lines_is_limited(int $count, ?string $exception): void
    {
        $lines = [];

        for ($i = 1; $i <= $count; $i++) {
            $lines[] = Orders::line(sprintf('0192f0c4-0000-7000-8000-%012d', $i));
        }

        if ($exception !== null) {
            $this->expectException($exception);
        }

        $order = Order::place(new OrderId(Orders::ORDER_ID), new SodaId(Orders::SODA_ID), new CustomerId(Orders::CUSTOMER_ID), $lines, Orders::at('12:00'));

        $this->assertCount($count, $order->lines);
    }

    /**
     * Line counts at and beyond the limits, with the exception each one raises.
     *
     * @return array<string, array{int, class-string<Throwable>|null}>
     */
    public static function lineCounts(): array
    {
        return [
            'no lines' => [0, OrderEmptyException::class],
            'one line' => [1, null],
            'twenty lines' => [20, null],
            'twenty-one lines' => [21, InvalidValueException::class],
        ];
    }

    /** The rules the API publishes with their own problem type carry a stable name. */
    public function test_named_rules_keep_their_problem_type(): void
    {
        $this->assertSame('pedido-vacio', OrderEmptyException::create()->rule());
        $this->assertSame('cantidad-invalida', QuantityOutOfRangeException::create()->rule());
    }

    /** The same dish cannot appear in two lines. */
    public function test_a_dish_appears_in_one_line_only(): void
    {
        $this->expectException(InvalidValueException::class);

        Order::place(
            new OrderId(Orders::ORDER_ID),
            new SodaId(Orders::SODA_ID),
            new CustomerId(Orders::CUSTOMER_ID),
            [Orders::line(), Orders::line(quantity: 3)],
            Orders::at('12:00'),
        );
    }

    /** Quantities go from 1 to 20, with a rule of their own, and prices keep the range of the catalog. */
    #[DataProvider('invalidLines')]
    public function test_invalid_lines_are_rejected(int $quantity, int $price, string $name, string $exception): void
    {
        $this->expectException($exception);

        Orders::line(quantity: $quantity, price: $price, name: $name);
    }

    /**
     * Lines that break one rule each, with the exception each one raises.
     *
     * @return array<string, array{int, int, string, class-string<Throwable>}>
     */
    public static function invalidLines(): array
    {
        return [
            'zero portions' => [0, 2_800, 'Casado', QuantityOutOfRangeException::class],
            'twenty-one portions' => [21, 2_800, 'Casado', QuantityOutOfRangeException::class],
            'price below the minimum' => [1, 99, 'Casado', InvalidValueException::class],
            'price above the maximum' => [1, 100_001, 'Casado', InvalidValueException::class],
            'blank name' => [1, 2_800, '  ', InvalidValueException::class],
            'name too long' => [1, 2_800, str_repeat('a', 121), InvalidValueException::class],
        ];
    }

    /** The order moves one step at a time and records the moment of each step. */
    public function test_order_advances_step_by_step_and_records_each_moment(): void
    {
        $order = Orders::paid();

        $order->advanceTo(OrderStatus::Confirmed, Orders::at('12:01'));
        $order->advanceTo(OrderStatus::Ready, Orders::at('12:15'));
        $order->advanceTo(OrderStatus::Delivered, Orders::at('12:20'));

        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertEquals(Orders::at('12:00'), $order->changedAt(OrderStatus::Pending));
        $this->assertEquals(Orders::at('12:01'), $order->changedAt(OrderStatus::Confirmed));
        $this->assertEquals(Orders::at('12:15'), $order->changedAt(OrderStatus::Ready));
        $this->assertEquals(Orders::at('12:20'), $order->changedAt(OrderStatus::Delivered));
        $this->assertNull($order->changedAt(OrderStatus::Cancelled));
    }

    /** A jump, a step back, staying in place or cancelling through advanceTo() is refused and changes nothing. */
    #[DataProvider('refusedSteps')]
    public function test_invalid_steps_are_refused(int $stepsFirst, OrderStatus $target): void
    {
        $order = Orders::paid();
        $flow = [OrderStatus::Confirmed, OrderStatus::Ready, OrderStatus::Delivered];

        for ($i = 0; $i < $stepsFirst; $i++) {
            $order->advanceTo($flow[$i], Orders::at('12:05'));
        }

        $status = $order->status;

        try {
            $order->advanceTo($target, Orders::at('12:30'));
            $this->fail("The order moved from {$status->value} to {$target->value}.");
        } catch (TransitionNotAllowedException $exception) {
            $this->assertSame('transicion-no-permitida', $exception->rule());
            $this->assertSame($status, $order->status);
            $this->assertNotEquals(Orders::at('12:30'), $order->changedAt($target));
        }
    }

    /**
     * Steps the kitchen flow does not allow, from the status reached after some valid steps.
     *
     * @return array<string, array{int, OrderStatus}>
     */
    public static function refusedSteps(): array
    {
        return [
            'pending to ready' => [0, OrderStatus::Ready],
            'pending to delivered' => [0, OrderStatus::Delivered],
            'pending to pending' => [0, OrderStatus::Pending],
            'confirmed back to pending' => [1, OrderStatus::Pending],
            'confirmed to delivered' => [1, OrderStatus::Delivered],
            'ready back to confirmed' => [2, OrderStatus::Confirmed],
            'delivered back to ready' => [3, OrderStatus::Ready],
            'pending to cancelled without cancel()' => [0, OrderStatus::Cancelled],
        ];
    }

    /** A pending or a confirmed order is cancelled with its origin and its moment. */
    #[DataProvider('cancellableSteps')]
    public function test_pending_or_confirmed_order_is_cancelled(int $stepsFirst): void
    {
        $order = Orders::paid();

        if ($stepsFirst === 1) {
            $order->advanceTo(OrderStatus::Confirmed, Orders::at('12:01'));
        }

        $this->assertTrue($order->cancel(CancellationOrigin::Kitchen, Orders::at('12:10')));
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(CancellationOrigin::Kitchen, $order->cancellationOrigin);
        $this->assertEquals(Orders::at('12:10'), $order->changedAt(OrderStatus::Cancelled));
    }

    /**
     * Statuses from which an order can be cancelled.
     *
     * @return array<string, array{int}>
     */
    public static function cancellableSteps(): array
    {
        return ['pending' => [0], 'confirmed' => [1]];
    }

    /** A ready or delivered order can no longer be cancelled. */
    public function test_ready_order_cannot_be_cancelled(): void
    {
        $order = Orders::paid();
        $order->advanceTo(OrderStatus::Confirmed, Orders::at('12:01'));
        $order->advanceTo(OrderStatus::Ready, Orders::at('12:15'));

        $this->expectException(TransitionNotAllowedException::class);

        $order->cancel(CancellationOrigin::Kitchen, Orders::at('12:16'));
    }

    /** Cancelling a cancelled order has no effect: the first origin and moment stay. */
    public function test_cancelling_twice_has_no_effect(): void
    {
        $order = Orders::pending();
        $order->cancel(CancellationOrigin::Customer, Orders::at('12:05'));

        $this->assertFalse($order->cancel(CancellationOrigin::Kitchen, Orders::at('12:20')));
        $this->assertSame(CancellationOrigin::Customer, $order->cancellationOrigin);
        $this->assertEquals(Orders::at('12:05'), $order->changedAt(OrderStatus::Cancelled));
    }

    /** A new order waits for its payment; the kitchen cannot confirm it before the payment is approved. */
    public function test_order_without_an_approved_payment_cannot_be_confirmed(): void
    {
        $order = Orders::pending();

        $this->assertSame(PaymentStatus::Pending, $order->paymentStatus);

        try {
            $order->advanceTo(OrderStatus::Confirmed, Orders::at('12:01'));
            $this->fail('An unpaid order was confirmed.');
        } catch (OrderNotPaidException $exception) {
            $this->assertSame('pedido-sin-pago', $exception->rule());
            $this->assertSame(OrderStatus::Pending, $order->status);
        }
    }

    /** Approving the payment records its moment once. */
    public function test_payment_is_approved_once(): void
    {
        $order = Orders::pending();

        $order->approvePayment(Orders::at('12:02'));
        $order->approvePayment(Orders::at('12:09'));

        $this->assertSame(PaymentStatus::Approved, $order->paymentStatus);
        $this->assertEquals(Orders::at('12:02'), $order->paidAt);
    }

    /** An unpaid order expires at its payment deadline, ten minutes after it was placed, and not a second earlier. */
    public function test_unpaid_order_expires_at_its_payment_deadline(): void
    {
        $order = Orders::pending();

        $this->assertEquals(Orders::at('12:10'), $order->paymentDeadline());
        $this->assertFalse($order->expire(Orders::at('12:09:59')));
        $this->assertSame(OrderStatus::Pending, $order->status);

        $this->assertTrue($order->expire(Orders::at('12:10')));
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(CancellationOrigin::PaymentTimeout, $order->cancellationOrigin);
        $this->assertFalse($order->expire(Orders::at('12:11')));
    }

    /** A paid order does not expire for lack of payment. */
    public function test_paid_order_does_not_expire_for_lack_of_payment(): void
    {
        $order = Orders::pending();
        $order->approvePayment(Orders::at('12:05'));

        $this->assertFalse($order->expire(Orders::at('12:10')));
        $this->assertSame(OrderStatus::Pending, $order->status);
    }

    /** A paid order the kitchen does not confirm expires ten minutes after its payment, for lack of confirmation. */
    public function test_paid_order_expires_at_its_confirmation_deadline(): void
    {
        $order = Orders::pending();
        $order->approvePayment(Orders::at('12:04'));

        $this->assertEquals(Orders::at('12:14'), $order->confirmationDeadline());
        $this->assertFalse($order->expire(Orders::at('12:13:59')));

        $this->assertTrue($order->expire(Orders::at('12:14')));
        $this->assertSame(CancellationOrigin::ConfirmationTimeout, $order->cancellationOrigin);
    }

    /** A confirmed order never expires. */
    public function test_confirmed_order_never_expires(): void
    {
        $order = Orders::paid();
        $order->advanceTo(OrderStatus::Confirmed, Orders::at('12:05'));

        $this->assertFalse($order->expire(Orders::at('13:00')));
        $this->assertSame(OrderStatus::Confirmed, $order->status);
    }

    /** An unpaid order has no confirmation deadline yet. */
    public function test_unpaid_order_has_no_confirmation_deadline(): void
    {
        $this->assertNull(Orders::pending()->confirmationDeadline());
    }
}
