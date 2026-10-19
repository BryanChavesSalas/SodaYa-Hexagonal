<?php

declare(strict_types=1);

namespace Tests\Unit\Billing\Payments\Domain;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\ValueObjects\Amount;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final class ChargeValueObjectsTest extends TestCase
{
    private const string ORDER_ID = '0192f0c4-0000-7000-8000-00000000a001';

    /** The amount is a whole number of colones greater than zero. */
    #[DataProvider('nonPositiveAmounts')]
    public function test_amount_must_be_positive(int $colones): void
    {
        $this->expectException(InvalidValueException::class);

        new Amount($colones);
    }

    /**
     * Amounts that cannot be charged.
     *
     * @return array<string, array{int}>
     */
    public static function nonPositiveAmounts(): array
    {
        return ['zero' => [0], 'negative' => [-5_600]];
    }

    /** The reference of the provider cannot be blank. */
    public function test_charge_reference_cannot_be_blank(): void
    {
        $this->expectException(InvalidValueException::class);

        new ChargeReference('   ');
    }

    /** Two references with the same value are the same reference; none equals a missing one. */
    public function test_charge_references_compare_by_value(): void
    {
        $reference = new ChargeReference('pi_3SodaYa');

        $this->assertTrue($reference->equals(new ChargeReference('pi_3SodaYa')));
        $this->assertFalse($reference->equals(new ChargeReference('pi_other')));
        $this->assertFalse($reference->equals(null));
    }

    /** A charge request needs the key that makes a retry harmless and the payment method of the customer. */
    #[DataProvider('incompleteChargeRequests')]
    public function test_charge_request_needs_its_key_and_its_payment_method(string $idempotencyKey, string $paymentMethod): void
    {
        $this->expectException(InvalidValueException::class);

        new ChargeRequest($idempotencyKey, new Amount(5_600), $paymentMethod, new OrderId(self::ORDER_ID));
    }

    /**
     * Charge requests missing a piece.
     *
     * @return array<string, array{string, string}>
     */
    public static function incompleteChargeRequests(): array
    {
        return ['without key' => ['', 'pm_card_visa'], 'without payment method' => ['pago-1', ' ']];
    }

    /** An approved charge always has the reference of the provider. */
    public function test_approved_result_needs_a_reference(): void
    {
        $this->expectException(InvalidValueException::class);

        new ChargeResult(PaymentStatus::Approved);
    }

    /** A rejected charge always says why the bank declined it. */
    public function test_rejected_result_needs_the_decline_code(): void
    {
        $this->expectException(InvalidValueException::class);

        new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_3SodaYa'));
    }

    /** Each result answers one of the three outcomes. */
    public function test_result_tells_its_outcome(): void
    {
        $approved = new ChargeResult(PaymentStatus::Approved, new ChargeReference('pi_3SodaYa'));
        $rejected = new ChargeResult(PaymentStatus::Rejected, new ChargeReference('pi_3SodaYa'), 'insufficient_funds');
        $pending = new ChargeResult(PaymentStatus::Pending);

        $this->assertTrue($approved->approved());
        $this->assertFalse($approved->rejected());
        $this->assertTrue($rejected->rejected());
        $this->assertSame('insufficient_funds', $rejected->declineCode);
        $this->assertTrue($pending->pending());
        $this->assertNull($pending->reference);
    }

    /** Only a rejected payment leaves room for another attempt: pending and approved are the live payment of the order. */
    public function test_only_a_rejected_payment_is_not_live(): void
    {
        $this->assertTrue(PaymentStatus::Pending->isLive());
        $this->assertTrue(PaymentStatus::Approved->isLive());
        $this->assertFalse(PaymentStatus::Rejected->isLive());
    }

    /** A provider that is down asks the client to come back in 30 seconds. */
    public function test_unavailable_gateway_asks_to_retry_in_thirty_seconds(): void
    {
        $this->assertSame(30, GatewayUnavailableException::unreachable()->retryAfterSeconds());
        $this->assertSame('payments.gateway_unreachable', GatewayUnavailableException::unreachable()->translationKey());
        $this->assertSame(['status' => 502], GatewayUnavailableException::status(502)->parameters());
    }
}
