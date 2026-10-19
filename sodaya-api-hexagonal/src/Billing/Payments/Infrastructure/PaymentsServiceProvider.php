<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Infrastructure\Stripe\StripeGateway;

final class PaymentsServiceProvider extends ServiceProvider
{
    /** Bind the gateway port to Stripe in test mode, with the credentials of the configuration: the only place that names the provider. */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => new StripeGateway(
            (string) config('services.stripe.secret'),
            (string) config('services.stripe.base_url'),
        ));
    }
}
