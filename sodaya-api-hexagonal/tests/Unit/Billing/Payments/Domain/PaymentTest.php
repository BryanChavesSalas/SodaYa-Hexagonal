<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Payments\Domain;

use PHPUnit\Framework\TestCase;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\ValueObjects\Amount;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\Billing\Payments;

final class PaymentTest extends TestCase
{
    /** A new attempt is pending, has no reference yet, counts as the live payment of its order and derives its key from its own identity. */
    public function test_opens_pending_with_a_key_derived_from_its_identity(): void
    {
        $payment = Payments::pending();

        $this->assertTrue($payment->isPending());
        $this->assertTrue($payment->isLive());
        $this->assertNull($payment->reference);
        $this->assertNull($payment->settledAt);
        $this->assertSame('pago-'.Payments::PAYMENT_ID, $payment->idempotencyKey);
        $this->assertSame(Payments::AMOUNT, $payment->amount->colones);
        $this->assertEquals(Payments::at('12:00'), $payment->openedAt);
    }

    /** The charge request carries the key, the amount of the order and the payment method the customer chose. */
    public function test_charge_request_carries_the_key_the_amount_and_the_payment_method(): void
    {
        $request = Payments::pending()->asChargeRequest('pm_card_visa');

        $this->assertSame('pago-'.Payments::PAYMENT_ID, $request->idempotencyKey);
        $this->assertSame(Payments::AMOUNT, $request->amount->colones);
        $this->assertSame('pm_card_visa', $request->paymentMethod);
        $this->assertSame(Payments::ORDER_ID, $request->orderId->value);
    }

    /** An approved charge closes the payment with the reference of the provider and the moment. */
    public function test_approved_charge_closes_the_payment(): void
    {
        $payment = Payments::pending();

        $changed = $payment->settle(new ChargeResult(PaymentStatus::Approved, new ChargeReference('pi_3SodaYa')), Payments::at('12:01'));

        $this->assertTrue($changed);
        $this->assertTrue($payment->isApproved());
        $this->assertTrue($payment->isLive());
        $this->assertSame('pi_3SodaYa', $payment->reference?->value);
        $this->assertNull($payment->declineCode);
        $this->assertEquals(Payments::at('12:01'), $payment->settledAt);
    }

    /** A declined charge closes the payment with the code of the bank, and the order is free for another attempt. */
    public function test_rejected_charge_closes_the_payment_with_the_code_of_the_bank(): void
    {
        $payment = Payments::pending();

        $payment->settle(new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_declined'), 'insufficient_funds'), Payments::at('12:01'));

        $this->assertTrue($payment->isRejected());
        $this->assertFalse($payment->isLive());
        $this->assertSame('insufficient_funds', $payment->declineCode);
        $this->assertSame('pi_declined', $payment->reference?->value);
        $this->assertEquals(Payments::at('12:01'), $payment->settledAt);
    }

    /** A provider still deciding leaves the payment pending; only its reference is kept, once. */
    public function test_pending_answer_keeps_the_payment_open_with_the_reference(): void
    {
        $payment = Payments::pending();

        $this->assertFalse($payment->settle(new ChargeResult(PaymentStatus::Pending), Payments::at('12:01')));
        $this->assertTrue($payment->settle(new ChargeResult(PaymentStatus::Pending, new ChargeReference('pi_slow')), Payments::at('12:01')));
        $this->assertFalse($payment->settle(new ChargeResult(PaymentStatus::Pending, new ChargeReference('pi_slow')), Payments::at('12:02')));

        $this->assertTrue($payment->isPending());
        $this->assertSame('pi_slow', $payment->reference?->value);
        $this->assertNull($payment->settledAt);
    }

    /** A closed payment never changes: settling it again answers false and keeps the first outcome. */
    public function test_a_closed_payment_never_changes(): void
    {
        $payment = Payments::approved();

        $changed = $payment->settle(new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_other'), 'generic_decline'), Payments::at('12:05'));

        $this->assertFalse($changed);
        $this->assertTrue($payment->isApproved());
        $this->assertSame('pi_fake', $payment->reference?->value);
        $this->assertEquals(Payments::at('12:00'), $payment->settledAt);
    }

    /** The provider is a short lowercase name, as it is stored. */
    public function test_provider_must_be_a_short_lowercase_name(): void
    {
        $this->expectException(InvalidValueException::class);

        Payment::open(
            new PaymentId(Payments::PAYMENT_ID),
            new OrderId(Payments::ORDER_ID),
            new SodaId(Payments::SODA_ID),
            new CustomerId(Payments::CUSTOMER_ID),
            'Stripe Inc.',
            new Amount(Payments::AMOUNT),
            Payments::at('12:00'),
        );
    }
}
