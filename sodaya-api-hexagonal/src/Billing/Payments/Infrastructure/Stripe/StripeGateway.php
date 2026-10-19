<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure\Stripe;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\Exceptions\GatewayUnavailableException;
use Src\Billing\Payments\Domain\ValueObjects\ChargeReference;
use Src\Billing\Payments\Domain\ValueObjects\ChargeRequest;
use Src\Billing\Payments\Domain\ValueObjects\ChargeResult;
use Src\Billing\Payments\Domain\ValueObjects\PaymentStatus;
use Throwable;

final readonly class StripeGateway implements PaymentGateway
{
    public const string PROVIDER = 'stripe';

    /** Prefix of every secret key of Stripe in test mode: the only credentials the project accepts. */
    public const string TEST_KEY_PREFIX = 'sk_test_';

    /** Seconds to wait for Stripe before giving up on a call. */
    public const int TIMEOUT_SECONDS = 10;

    /** Attempts of a call whose connection fails, and the milliseconds to wait between them. */
    public const int ATTEMPTS = 3;

    public const int RETRY_DELAY_MILLISECONDS = 200;

    /** Keep the test secret key and the base URL of the API; a key that is not a test key is refused. */
    public function __construct(
        private string $secret,
        private string $baseUrl = 'https://api.stripe.com/v1',
    ) {
        if (! str_starts_with($secret, self::TEST_KEY_PREFIX)) {
            throw new InvalidArgumentException('Only Stripe test credentials are accepted: the secret key must start with sk_test_.');
        }
    }

    /** Name of the provider. */
    public function provider(): string
    {
        return self::PROVIDER;
    }

    /**
     * Create and confirm a payment intent in one call. Stripe wants the amount in céntimos, because CRC is not one
     * of its zero-decimal currencies, and answers a charge it already saw with the same Idempotency-Key instead of making another.
     */
    public function charge(ChargeRequest $request): ChargeResult
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http
            ->withHeaders(['Idempotency-Key' => $request->idempotencyKey])
            ->post('/payment_intents', [
                'amount' => $request->amount->colones * 100,
                'currency' => 'crc',
                'payment_method' => $request->paymentMethod,
                'payment_method_types' => ['card'],
                'confirm' => 'true',
                'metadata' => ['order_id' => $request->orderId->value],
            ]));

        return $this->translate($response);
    }

    /** Read the payment intent as it is now. */
    public function status(ChargeReference $reference): ChargeResult
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->get('/payment_intents/'.$reference->value));

        return $this->translate($response);
    }

    /**
     * Send one call with the secret, as a form and with the timeout, and retry only a connection that failed:
     * a timeout is a doubt the same key resolves, while a 402 is a decision of the bank. Any other failure is not
     * a decision and becomes GatewayUnavailableException.
     *
     * @param  Closure(PendingRequest): Response  $request
     */
    private function send(Closure $request): Response
    {
        $http = Http::baseUrl($this->baseUrl)
            ->withToken($this->secret)
            ->asForm()
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS)
            ->retry(
                self::ATTEMPTS,
                self::RETRY_DELAY_MILLISECONDS,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            );

        try {
            $response = $request($http);
        } catch (ConnectionException) {
            throw GatewayUnavailableException::unreachable();
        }

        if ($response->failed() && $response->status() !== 402) {
            throw GatewayUnavailableException::status($response->status());
        }

        return $response;
    }

    /** Read the outcome from the shape of a Stripe answer: 402 carries the error of the card, 200 the status of the intent. */
    private function translate(Response $response): ChargeResult
    {
        if ($response->status() === 402) {
            return new ChargeResult(
                PaymentStatus::Rejected,
                $this->referenceOf($response->json('error.payment_intent.id')),
                $this->declineCodeOf($response->json('error.decline_code'), $response->json('error.code')),
            );
        }

        $reference = $this->referenceOf($response->json('id'));

        return match ($response->json('status')) {
            'succeeded' => new ChargeResult(
                PaymentStatus::Approved,
                $reference ?? throw GatewayUnavailableException::status($response->status()),
            ),
            'requires_payment_method', 'canceled' => new ChargeResult(
                PaymentStatus::Rejected,
                $reference,
                $this->declineCodeOf($response->json('last_payment_error.decline_code'), $response->json('last_payment_error.code')),
            ),
            default => new ChargeResult(PaymentStatus::Pending, $reference),
        };
    }

    /** The identifier of the intent, when the answer carries one. */
    private function referenceOf(mixed $id): ?ChargeReference
    {
        return is_string($id) && $id !== '' ? new ChargeReference($id) : null;
    }

    /** The most specific reason the bank gave, falling back to the generic code of a declined card. */
    private function declineCodeOf(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return 'card_declined';
    }
}
