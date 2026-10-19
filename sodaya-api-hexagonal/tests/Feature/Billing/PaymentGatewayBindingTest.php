<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Infrastructure\Stripe\StripeGateway;
use Tests\TestCase;

final class PaymentGatewayBindingTest extends TestCase
{
    /** The container hands out the Stripe adapter for the port, built from the test key of the configuration. */
    public function test_the_container_resolves_stripe_for_the_port(): void
    {
        $gateway = $this->app->make(PaymentGateway::class);

        $this->assertInstanceOf(StripeGateway::class, $gateway);
        $this->assertSame('stripe', $gateway->provider());
        $this->assertSame('sk_test_sodaya', config('services.stripe.secret'));
    }

    /** A key that is not a test key, or no key at all, stops the adapter from being built. */
    #[DataProvider('forbiddenSecrets')]
    public function test_only_a_test_key_builds_the_adapter(?string $secret): void
    {
        config(['services.stripe.secret' => $secret]);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(PaymentGateway::class);
    }

    /**
     * Secrets the project refuses.
     *
     * @return array<string, array{string|null}>
     */
    public static function forbiddenSecrets(): array
    {
        return ['live key' => ['sk_live_sodaya'], 'restricted key' => ['rk_test_sodaya'], 'missing' => [null]];
    }
}
