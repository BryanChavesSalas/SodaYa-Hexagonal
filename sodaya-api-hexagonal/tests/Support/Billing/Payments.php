<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use DateTimeImmutable;
use DateTimeZone;
use Src\Billing\Payments\Domain\Entities\Payment;
use Src\Billing\Payments\Domain\ValueObjects\Amount;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\CustomerId;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\ValueObjects\SodaId;

final class Payments
{
    public const string PAYMENT_ID = '0192f0c4-0000-7000-8000-00000000e001';

    public const string ORDER_ID = '0192f0c4-0000-7000-8000-00000000a001';

    public const string SODA_ID = '0192f0c4-0000-7000-8000-00000000b001';

    public const string CUSTOMER_ID = '0192f0c4-0000-7000-8000-00000000c001';

    public const int AMOUNT = 5_600;

    /** An attempt of ₡5600 just opened with the fake provider, at 12:00 in Costa Rica. */
    public static function pending(string $paymentId = self::PAYMENT_ID, string $orderId = self::ORDER_ID): Payment
    {
        return Payment::open(
            new PaymentId($paymentId),
            new OrderId($orderId),
            new SodaId(self::SODA_ID),
            new CustomerId(self::CUSTOMER_ID),
            FakePaymentGateway::PROVIDER,
            new Amount(self::AMOUNT),
            self::at('12:00'),
        );
    }

    /** The same attempt, approved by the provider at 12:00. */
    public static function approved(string $paymentId = self::PAYMENT_ID, string $orderId = self::ORDER_ID): Payment
    {
        $payment = self::pending($paymentId, $orderId);
        $payment->settle(new ChargeResult(PaymentStatus::Approved, new ChargeReference(FakePaymentGateway::REFERENCE)), self::at('12:00'));

        return $payment;
    }

    /** The same attempt, declined by the bank at 12:00. */
    public static function rejected(string $paymentId = self::PAYMENT_ID, string $orderId = self::ORDER_ID): Payment
    {
        $payment = self::pending($paymentId, $orderId);
        $payment->settle(new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_declined'), 'generic_decline'), self::at('12:00'));

        return $payment;
    }

    /** A moment of 2026-10-05 in Costa Rica. */
    public static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable("2026-10-05 {$time}", new DateTimeZone('America/Costa_Rica'));
    }
}
