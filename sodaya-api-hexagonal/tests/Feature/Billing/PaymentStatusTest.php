<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Billing\PaysAnOrder;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class PaymentStatusTest extends TestCase
{
    use PaysAnOrder, RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    /** A customer with a pending order; Stripe is always faked. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerWithPendingOrder();
    }

    /** Asking for a pending payment consults Stripe and records what it answers; the state in the query is ignored and a closed payment is not consulted again. */
    public function test_asking_for_a_pending_payment_consults_the_provider(): void
    {
        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_slow', 'status' => 'processing'], 200),
            'api.stripe.com/v1/payment_intents/pi_slow' => Http::response(['id' => 'pi_slow', 'status' => 'succeeded'], 200),
        ]);
        $this->postJson($this->url(), ['metodo_pago' => 'pm_card_visa'])->assertAccepted();

        $this->getJson($this->url().'?estado=rechazado')
            ->assertOk()
            ->assertJsonPath('data.estado', 'aprobado')
            ->assertJsonPath('data.referencia', 'pi_slow');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.stripe.com/v1/payment_intents/pi_slow');
        $this->scopeQueriesTo($this->order->soda_id);
        $this->assertSame('approved', $this->order->refresh()->payment_status);

        $this->getJson($this->url())->assertOk()->assertJsonPath('data.estado', 'aprobado');
        Http::assertSentCount(2);
    }

    /** An order nobody tried to pay has no payment to show. */
    public function test_an_order_without_payments_answers_not_found(): void
    {
        Http::fake();

        $this->getJson($this->url())->assertNotFound();

        Http::assertNothingSent();
    }

    /** The payment of an order of another customer is not found, like the order itself. */
    public function test_the_payment_of_another_customer_is_not_found(): void
    {
        Http::fake();

        $this->getJson($this->url('0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99'))
            ->assertNotFound()
            ->assertJsonPath('type', 'http://localhost/problemas/pedido-no-encontrado');

        Http::assertNothingSent();
    }
}
