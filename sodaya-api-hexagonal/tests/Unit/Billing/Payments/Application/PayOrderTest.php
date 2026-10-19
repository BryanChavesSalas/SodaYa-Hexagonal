<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Payments\Application;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Application\DTOs\PayableOrder;
use Src\Billing\Payments\Application\DTOs\PayOrderCommand;
use Src\Billing\Payments\Application\UseCases\PayOrder;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\Exceptions\OrderAlreadyPaidException;
use Src\Billing\Payments\Domain\Exceptions\OrderNotFoundException;
use Src\Billing\Payments\Domain\Exceptions\OrderNotPayableException;
use Src\Billing\Payments\Domain\Exceptions\PaymentRejectedException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Billing\FakeOrderPayments;
use Tests\Support\Billing\FakePayableOrders;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\Support\Billing\InMemoryPaymentRepository;
use Tests\Support\Billing\Payments;
use Tests\Support\Shared\RecordingTransactionRunner;

final class PayOrderTest extends TestCase
{
    private const string PAID_ORDER = '0192f0c4-0000-7000-8000-00000000a002';

    private const string CANCELLED_ORDER = '0192f0c4-0000-7000-8000-00000000a003';

    private const string OTHER_CUSTOMER = '0192f0c4-0000-7000-8000-00000000c002';

    private InMemoryPaymentRepository $payments;

    private FakePaymentGateway $gateway;

    private FakeOrderPayments $orderPayments;

    private RecordingTransactionRunner $transaction;

    private PayOrder $payOrder;

    /** Wire the use case to in-memory adapters: a customer with a pending order of ₡5600, one already paid and one cancelled. */
    protected function setUp(): void
    {
        $this->payments = new InMemoryPaymentRepository;
        $this->gateway = new FakePaymentGateway;
        $this->orderPayments = new FakeOrderPayments;
        $this->transaction = new RecordingTransactionRunner;
        $this->payOrder = new PayOrder(
            new FakePayableOrders([
                new PayableOrder(Payments::ORDER_ID, Payments::SODA_ID, Payments::CUSTOMER_ID, Payments::AMOUNT, pending: true, paid: false),
                new PayableOrder(self::PAID_ORDER, Payments::SODA_ID, Payments::CUSTOMER_ID, Payments::AMOUNT, pending: true, paid: true),
                new PayableOrder(self::CANCELLED_ORDER, Payments::SODA_ID, Payments::CUSTOMER_ID, Payments::AMOUNT, pending: false, paid: false),
            ]),
            $this->payments,
            $this->gateway,
            $this->orderPayments,
            $this->transaction,
        );
    }

    /** Approved: the payment closes with the reference, the order is marked paid, and two transactions committed. */
    public function test_approved_charge_leaves_the_order_paid(): void
    {
        $payment = $this->payOrder->execute($this->command(), Payments::at('12:00'));

        $this->assertTrue($payment->isApproved());
        $this->assertSame(FakePaymentGateway::REFERENCE, $payment->reference?->value);
        $this->assertSame(FakePaymentGateway::PROVIDER, $payment->provider);
        $this->assertTrue($this->payments->findForUpdate($payment->id, new SodaId(Payments::SODA_ID))?->isApproved());
        $this->assertEquals([Payments::ORDER_ID => Payments::at('12:00')], $this->orderPayments->paid);
        $this->assertSame(2, $this->transaction->committed());
        $this->assertSame(0, $this->transaction->rolledBack());
    }

    /** The amount comes from the stored order and the key from the attempt; the command has no amount to send. */
    public function test_the_provider_receives_the_amount_of_the_order_with_the_key_of_the_attempt(): void
    {
        $payment = $this->payOrder->execute($this->command(), Payments::at('12:00'));

        $charge = $this->gateway->charges[0];
        $this->assertSame(Payments::AMOUNT, $charge->amount->colones);
        $this->assertSame('pago-'.$payment->id->value, $charge->idempotencyKey);
        $this->assertSame('pm_card_visa', $charge->paymentMethod);
        $this->assertSame(Payments::ORDER_ID, $charge->orderId->value);
        $this->assertSame(
            ['orderId', 'customerId', 'paymentMethod'],
            array_map(fn (ReflectionProperty $property): string => $property->getName(), new ReflectionClass(PayOrderCommand::class)->getProperties()),
        );
    }

    /** Rejected: the payment is saved with the code of the bank, the order stays unpaid, and the customer can try again. */
    public function test_rejected_charge_is_saved_and_reported_and_leaves_room_to_retry(): void
    {
        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_declined'), 'insufficient_funds'));

        try {
            $this->payOrder->execute($this->command(), Payments::at('12:00'));
            $this->fail('A declined charge was not reported.');
        } catch (PaymentRejectedException $exception) {
            $this->assertSame('pago-rechazado', $exception->rule());
            $this->assertSame(['code' => 'insufficient_funds'], $exception->parameters());
        }

        $rejected = $this->payments->latestOf(new OrderId(Payments::ORDER_ID), new SodaId(Payments::SODA_ID));
        $this->assertTrue($rejected?->isRejected());
        $this->assertSame('insufficient_funds', $rejected->declineCode);
        $this->assertSame([], $this->orderPayments->paid);
        $this->assertSame(2, $this->transaction->committed());

        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Approved, new ChargeReference('pi_second')));
        $retry = $this->payOrder->execute($this->command(), Payments::at('12:05'));

        $this->assertNotSame($rejected->id->value, $retry->id->value);
        $this->assertTrue($retry->isApproved());
        $this->assertSame(2, $this->payments->count());
    }

    /** Provider down: the failure propagates, the attempt stays pending without a reference, and nothing else is written. */
    public function test_unavailable_provider_leaves_the_payment_pending(): void
    {
        $this->gateway->answerWith(GatewayUnavailableException::unreachable());

        try {
            $this->payOrder->execute($this->command(), Payments::at('12:00'));
            $this->fail('A provider that is down was not reported.');
        } catch (GatewayUnavailableException) {
        }

        $pending = $this->payments->latestOf(new OrderId(Payments::ORDER_ID), new SodaId(Payments::SODA_ID));
        $this->assertTrue($pending?->isPending());
        $this->assertNull($pending->reference);
        $this->assertSame([], $this->orderPayments->paid);
        $this->assertSame(1, $this->transaction->committed());
        $this->assertSame(0, $this->payments->saves);
    }

    /** Pressing pay twice reuses the live attempt: the provider receives the same key and one payment exists. */
    public function test_a_second_request_reuses_the_live_attempt_with_the_same_key(): void
    {
        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Pending, new ChargeReference('pi_slow')));

        $first = $this->payOrder->execute($this->command(), Payments::at('12:00'));
        $second = $this->payOrder->execute($this->command(), Payments::at('12:01'));

        $this->assertSame($first->id->value, $second->id->value);
        $this->assertSame(1, $this->payments->count());
        $this->assertCount(2, $this->gateway->charges);
        $this->assertSame($this->gateway->charges[0]->idempotencyKey, $this->gateway->charges[1]->idempotencyKey);
        $this->assertTrue($second->isPending());
        $this->assertSame('pi_slow', $second->reference?->value);
    }

    /** The provider is called outside of any transaction. */
    public function test_the_provider_is_asked_outside_of_any_transaction(): void
    {
        $transaction = $this->transaction;
        $gateway = new class($transaction) implements PaymentGateway
        {
            public ?bool $calledInsideTransaction = null;

            public function __construct(private readonly RecordingTransactionRunner $transaction) {}

            public function provider(): string
            {
                return 'fake';
            }

            public function charge(ChargeRequest $request): ChargeResult
            {
                $this->calledInsideTransaction = $this->transaction->inProgress();

                return new ChargeResult(PaymentStatus::Approved, new ChargeReference('pi_outside'));
            }

            public function status(ChargeReference $reference): ChargeResult
            {
                return new ChargeResult(PaymentStatus::Approved, $reference);
            }
        };
        $payOrder = new PayOrder(
            new FakePayableOrders([new PayableOrder(Payments::ORDER_ID, Payments::SODA_ID, Payments::CUSTOMER_ID, Payments::AMOUNT, true, false)]),
            $this->payments,
            $gateway,
            $this->orderPayments,
            $transaction,
        );

        $payOrder->execute($this->command(), Payments::at('12:00'));

        $this->assertFalse($gateway->calledInsideTransaction);
        $this->assertSame(2, $transaction->runs());
    }

    /** An order already paid answers pedido-ya-pagado before anything is charged. */
    public function test_an_order_already_paid_is_refused(): void
    {
        $this->expectException(OrderAlreadyPaidException::class);

        try {
            $this->payOrder->execute($this->command(self::PAID_ORDER), Payments::at('12:00'));
        } finally {
            $this->assertSame([], $this->gateway->charges);
            $this->assertSame(0, $this->payments->count());
            $this->assertSame(1, $this->transaction->rolledBack());
        }
    }

    /** An order that is no longer pending answers pedido-no-pagable before anything is charged. */
    public function test_an_order_that_is_not_pending_is_refused(): void
    {
        $this->expectException(OrderNotPayableException::class);

        try {
            $this->payOrder->execute($this->command(self::CANCELLED_ORDER), Payments::at('12:00'));
        } finally {
            $this->assertSame([], $this->gateway->charges);
            $this->assertSame(0, $this->payments->count());
        }
    }

    /** An order of another customer is not found, the same as one that does not exist. */
    public function test_an_order_of_another_customer_is_not_found(): void
    {
        $this->expectException(OrderNotFoundException::class);

        try {
            $this->payOrder->execute(new PayOrderCommand(Payments::ORDER_ID, self::OTHER_CUSTOMER, 'pm_card_visa'), Payments::at('12:00'));
        } finally {
            $this->assertSame([], $this->gateway->charges);
        }
    }

    /** The command of the customer of the scenario for an order, with the test card. */
    private function command(string $orderId = Payments::ORDER_ID): PayOrderCommand
    {
        return new PayOrderCommand($orderId, Payments::CUSTOMER_ID, 'pm_card_visa');
    }
}
