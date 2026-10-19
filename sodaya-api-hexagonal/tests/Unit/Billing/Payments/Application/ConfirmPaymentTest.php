<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Payments\Application;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Src\Billing\Payments\Application\DTOs\ConfirmPaymentCommand;
use Src\Billing\Payments\Application\DTOs\PayableOrder;
use Src\Billing\Payments\Application\UseCases\ConfirmPayment;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\Exceptions\OrderNotFoundException;
use Src\Billing\Payments\Domain\Exceptions\PaymentNotFoundException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Billing\FakeOrderPayments;
use Tests\Support\Billing\FakePayableOrders;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\Support\Billing\InMemoryPaymentRepository;
use Tests\Support\Billing\Payments;
use Tests\Support\Shared\RecordingTransactionRunner;

final class ConfirmPaymentTest extends TestCase
{
    private const string UNPAID_ORDER = '0192f0c4-0000-7000-8000-00000000a002';

    private const string OTHER_CUSTOMER = '0192f0c4-0000-7000-8000-00000000c002';

    private InMemoryPaymentRepository $payments;

    private FakePaymentGateway $gateway;

    private FakeOrderPayments $orderPayments;

    private RecordingTransactionRunner $transaction;

    private ConfirmPayment $confirmPayment;

    /** Wire the use case to in-memory adapters: a customer with a pending order whose payment the provider has not decided, and one nobody tried to pay. */
    protected function setUp(): void
    {
        $this->payments = new InMemoryPaymentRepository;
        $this->gateway = new FakePaymentGateway;
        $this->orderPayments = new FakeOrderPayments;
        $this->transaction = new RecordingTransactionRunner;
        $this->confirmPayment = new ConfirmPayment(
            new FakePayableOrders([
                new PayableOrder(Payments::ORDER_ID, Payments::SODA_ID, Payments::CUSTOMER_ID, Payments::AMOUNT, pending: true, paid: false),
                new PayableOrder(self::UNPAID_ORDER, Payments::SODA_ID, Payments::CUSTOMER_ID, Payments::AMOUNT, pending: true, paid: false),
            ]),
            $this->payments,
            $this->gateway,
            $this->orderPayments,
            $this->transaction,
        );
    }

    /** The provider says approved: the payment closes, the order is marked paid, in one transaction. */
    public function test_approved_answer_closes_the_payment_and_marks_the_order_paid(): void
    {
        $this->open($this->pendingWithReference());
        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Approved, new ChargeReference('pi_slow')));

        $payment = $this->confirmPayment->execute($this->command(), Payments::at('12:05'));

        $this->assertTrue($payment->isApproved());
        $this->assertEquals([new ChargeReference('pi_slow')], $this->gateway->statusChecks);
        $this->assertTrue($this->payments->findForUpdate($payment->id, new SodaId(Payments::SODA_ID))?->isApproved());
        $this->assertEquals([Payments::ORDER_ID => Payments::at('12:05')], $this->orderPayments->paid);
        $this->assertSame(1, $this->transaction->committed());
    }

    /** The provider says rejected: the payment closes with the code of the bank and the order stays unpaid. */
    public function test_rejected_answer_closes_the_payment_and_leaves_the_order_unpaid(): void
    {
        $this->open($this->pendingWithReference());
        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_slow'), 'generic_decline'));

        $payment = $this->confirmPayment->execute($this->command(), Payments::at('12:05'));

        $this->assertTrue($payment->isRejected());
        $this->assertSame('generic_decline', $payment->declineCode);
        $this->assertSame([], $this->orderPayments->paid);
        $this->assertSame(1, $this->payments->saves);
    }

    /** The provider is still deciding: nothing changes and nothing is written. */
    public function test_pending_answer_changes_nothing(): void
    {
        $this->open($this->pendingWithReference());
        $this->gateway->answerWith(new ChargeResult(PaymentStatus::Pending, new ChargeReference('pi_slow')));

        $payment = $this->confirmPayment->execute($this->command(), Payments::at('12:05'));

        $this->assertTrue($payment->isPending());
        $this->assertSame(0, $this->payments->saves);
        $this->assertSame([], $this->orderPayments->paid);
    }

    /** A pending payment without a reference never reached the provider: there is nothing to ask. */
    public function test_pending_payment_without_reference_is_not_asked_about(): void
    {
        $this->open(Payments::pending());

        $payment = $this->confirmPayment->execute($this->command(), Payments::at('12:05'));

        $this->assertTrue($payment->isPending());
        $this->assertSame([], $this->gateway->statusChecks);
        $this->assertSame(0, $this->transaction->runs());
    }

    /** A payment the provider already decided is answered as stored, without asking again. */
    public function test_closed_payment_is_answered_as_stored(): void
    {
        $this->open(Payments::approved());

        $payment = $this->confirmPayment->execute($this->command(), Payments::at('12:05'));

        $this->assertTrue($payment->isApproved());
        $this->assertSame([], $this->gateway->statusChecks);
        $this->assertSame(0, $this->transaction->runs());
    }

    /** Run twice, the second run finds the payment closed: it asks nothing and writes nothing. */
    public function test_running_twice_changes_nothing_the_second_time(): void
    {
        $this->open($this->pendingWithReference());

        $this->confirmPayment->execute($this->command(), Payments::at('12:05'));
        $this->confirmPayment->execute($this->command(), Payments::at('12:06'));

        $this->assertCount(1, $this->gateway->statusChecks);
        $this->assertSame(1, $this->payments->saves);
        $this->assertEquals([Payments::ORDER_ID => Payments::at('12:05')], $this->orderPayments->paid);
        $this->assertSame(1, $this->transaction->runs());
    }

    /** A provider that is down lets the 503 through and leaves the payment pending. */
    public function test_unavailable_provider_leaves_the_payment_pending(): void
    {
        $this->open($this->pendingWithReference());
        $this->gateway->answerWith(GatewayUnavailableException::unreachable());

        $this->expectException(GatewayUnavailableException::class);

        try {
            $this->confirmPayment->execute($this->command(), Payments::at('12:05'));
        } finally {
            $this->assertTrue($this->payments->latestOf($this->pendingWithReference()->orderId, new SodaId(Payments::SODA_ID))?->isPending());
            $this->assertSame(0, $this->transaction->runs());
        }
    }

    /** An order nobody tried to pay has no payment to confirm. */
    public function test_order_without_payments_is_not_found(): void
    {
        $this->expectException(PaymentNotFoundException::class);

        $this->confirmPayment->execute(new ConfirmPaymentCommand(self::UNPAID_ORDER, Payments::CUSTOMER_ID), Payments::at('12:05'));
    }

    /** An order of another customer is not found, the same as one that does not exist. */
    public function test_order_of_another_customer_is_not_found(): void
    {
        $this->open($this->pendingWithReference());

        $this->expectException(OrderNotFoundException::class);

        $this->confirmPayment->execute(new ConfirmPaymentCommand(Payments::ORDER_ID, self::OTHER_CUSTOMER), Payments::at('12:05'));
    }

    /** The command carries nothing about the state: what the browser says is never used. */
    public function test_command_carries_no_state(): void
    {
        $this->assertSame(
            ['orderId', 'customerId'],
            array_map(fn (ReflectionProperty $property): string => $property->getName(), new ReflectionClass(ConfirmPaymentCommand::class)->getProperties()),
        );
    }

    /** The attempt of the scenario after the provider answered that it was still deciding. */
    private function pendingWithReference(): Payment
    {
        $payment = Payments::pending();
        $payment->settle(new ChargeResult(PaymentStatus::Pending, new ChargeReference('pi_slow')), Payments::at('12:00'));

        return $payment;
    }

    /** Store a payment as PayOrder would have left it. */
    private function open(Payment $payment): void
    {
        $this->payments->open($payment);
    }

    /** The command of the customer of the scenario for the order with the pending payment. */
    private function command(): ConfirmPaymentCommand
    {
        return new ConfirmPaymentCommand(Payments::ORDER_ID, Payments::CUSTOMER_ID);
    }
}
