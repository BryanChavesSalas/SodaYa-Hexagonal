<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Billing\Payments\Application\Contracts\PaymentGateway;
use Src\Billing\Payments\Domain\Contracts\PaymentRepository;
use Src\Billing\Payments\Infrastructure\Persistence\Repositories\EloquentPaymentRepository;
use Src\Billing\Payments\Infrastructure\Stripe\StripeGateway;

final class PaymentsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PaymentRepository::class => EloquentPaymentRepository::class,
    ];

    /** Bind the gateway port to Stripe in test mode, with the credentials of the configuration: the only place that names the provider. */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn (): PaymentGateway => new StripeGateway(
            (string) config('services.stripe.secret'),
            (string) config('services.stripe.base_url'),
        ));
    }
}
