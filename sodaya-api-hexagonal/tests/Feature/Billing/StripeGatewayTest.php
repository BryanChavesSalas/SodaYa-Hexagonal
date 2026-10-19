<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\ValueObjects\Amount;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\OrderId;
use Src\Billing\Payments\Infrastructure\Stripe\StripeGateway;
use Tests\TestCase;

final class StripeGatewayTest extends TestCase
{
    private const string ORDER_ID = '0192f0c4-0000-7000-8000-00000000a001';

    private const string IDEMPOTENCY_KEY = 'pago-0192f0c4-0000-7000-8000-00000000e001';

    private StripeGateway $gateway;

    /** The adapter with a test key, against a faked Stripe. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = new StripeGateway('sk_test_sodaya');
    }

    /** An approved intent is an approved charge; what travels is the exact amount in céntimos, the currency, the key, the test secret and the confirmation. */
    public function test_approved_charge_sends_the_amount_in_centimos_with_the_key_and_the_test_secret(): void
    {
        $options = [];
        Http::fake(['api.stripe.com/v1/payment_intents' => function (Request $request, array $sent) use (&$options) {
            $options = $sent;

            return Http::response(['id' => 'pi_3SodaYa', 'status' => 'succeeded'], 200);
        }]);

        $result = $this->gateway->charge($this->request());

        $this->assertTrue($result->approved());
        $this->assertSame('pi_3SodaYa', $result->reference?->value);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.stripe.com/v1/payment_intents'
            && $request->isForm()
            && $request['amount'] === 560_000
            && $request['currency'] === 'crc'
            && $request['payment_method'] === 'pm_card_visa'
            && $request['payment_method_types'] === ['card']
            && $request['confirm'] === 'true'
            && $request['metadata'] === ['order_id' => self::ORDER_ID]
            && $request->hasHeader('Idempotency-Key', self::IDEMPOTENCY_KEY)
            && $request->hasHeader('Authorization', 'Bearer sk_test_sodaya'));
        Http::assertSentCount(1);
        $this->assertSame(StripeGateway::TIMEOUT_SECONDS, $options['timeout']);
    }

    /** A declined card is a rejected charge with the code of the bank and the intent Stripe created: a business outcome, not an exception. */
    public function test_declined_card_is_a_rejected_charge_not_an_exception(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response([
            'error' => [
                'type' => 'card_error',
                'code' => 'card_declined',
                'decline_code' => 'insufficient_funds',
                'payment_intent' => ['id' => 'pi_declined', 'status' => 'requires_payment_method'],
            ],
        ], 402)]);

        $result = $this->gateway->charge($this->request());

        $this->assertTrue($result->rejected());
        $this->assertSame('insufficient_funds', $result->declineCode);
        $this->assertSame('pi_declined', $result->reference?->value);
        Http::assertSentCount(1);
    }

    /** A connection that fails is tried three times with the same key and then reported as unavailable. */
    public function test_connection_failure_is_retried_three_times_and_then_unavailable(): void
    {
        Http::fake(['api.stripe.com/*' => Http::failedConnection()]);

        try {
            $this->gateway->charge($this->request());
            $this->fail('A provider that does not answer was not reported.');
        } catch (GatewayUnavailableException $exception) {
            $this->assertSame('payments.gateway_unreachable', $exception->translationKey());
        }

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', self::IDEMPOTENCY_KEY));
    }

    /** An error that is not a decision of the bank, such as a 5xx or a wrong key, is unavailable without retrying. */
    #[DataProvider('failures')]
    public function test_error_that_is_not_a_decision_is_unavailable_without_retrying(int $status): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['error' => ['type' => 'api_error']], $status)]);

        try {
            $this->gateway->charge($this->request());
            $this->fail('A provider that failed was not reported.');
        } catch (GatewayUnavailableException $exception) {
            $this->assertSame('payments.gateway_failed', $exception->translationKey());
            $this->assertSame(['status' => $status], $exception->parameters());
        }

        Http::assertSentCount(1);
    }

    /**
     * Statuses Stripe answers with when it cannot decide.
     *
     * @return array<string, array{int}>
     */
    public static function failures(): array
    {
        return ['server error' => [503], 'wrong key' => [401], 'key in use by another request' => [409]];
    }

    /** The status of a charge is read from its payment intent with the test secret. */
    public function test_status_reads_the_payment_intent(): void
    {
        Http::fake(['api.stripe.com/v1/payment_intents/pi_3SodaYa' => Http::response(['id' => 'pi_3SodaYa', 'status' => 'succeeded'])]);

        $result = $this->gateway->status(new ChargeReference('pi_3SodaYa'));

        $this->assertTrue($result->approved());
        $this->assertSame('pi_3SodaYa', $result->reference?->value);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.stripe.com/v1/payment_intents/pi_3SodaYa'
            && $request->hasHeader('Authorization', 'Bearer sk_test_sodaya'));
    }

    /** An intent that needs another payment method was declined: the status tells the code of the bank. */
    public function test_status_of_a_declined_intent_is_rejected(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'pi_declined',
            'status' => 'requires_payment_method',
            'last_payment_error' => ['code' => 'card_declined', 'decline_code' => 'generic_decline'],
        ])]);

        $result = $this->gateway->status(new ChargeReference('pi_declined'));

        $this->assertTrue($result->rejected());
        $this->assertSame('generic_decline', $result->declineCode);
    }

    /** An intent Stripe is still processing is neither approved nor rejected. */
    public function test_status_of_an_intent_still_processing_is_pending(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_slow', 'status' => 'processing'])]);

        $result = $this->gateway->status(new ChargeReference('pi_slow'));

        $this->assertTrue($result->pending());
        $this->assertSame('pi_slow', $result->reference?->value);
    }

    /** Only test credentials are accepted: a live key never reaches a call. */
    public function test_only_test_credentials_are_accepted(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StripeGateway('sk_live_sodaya');
    }

    /** The base URL can point at a local mock of the API, for manual checks without the network. */
    public function test_base_url_can_point_at_a_mock(): void
    {
        Http::fake(['stripe.test/*' => Http::response(['id' => 'pi_mock', 'status' => 'succeeded'])]);

        new StripeGateway('sk_test_sodaya', 'http://stripe.test/v1')->charge($this->request());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://stripe.test/v1/payment_intents');
    }

    /** A charge of ₡5600 for the order of the scenario with the test card of Stripe. */
    private function request(): ChargeRequest
    {
        return new ChargeRequest(self::IDEMPOTENCY_KEY, new Amount(5_600), 'pm_card_visa', new OrderId(self::ORDER_ID));
    }
}
