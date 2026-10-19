<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Support\Facades\Http;
use Src\Billing\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Tests\Support\Billing\PaysAnOrder;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class PaymentOutcomesTest extends TestCase
{
    use PaysAnOrder, RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    /** A customer with a pending order; Stripe is always faked. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerWithPendingOrder();
    }

    /** Rejected: 402 pago-rechazado, the payment saved with the code of the bank, the order unpaid, and a retry that opens another attempt. */
    public function test_declined_card_answers_payment_rejected_and_allows_a_retry(): void
    {
        Http::fake(['api.stripe.com/v1/payment_intents' => Http::sequence()->push(self::DECLINED, 402)->push(self::APPROVED, 200)]);

        $this->postJson($this->url(), ['metodo_pago' => 'pm_card_visa_chargeDeclined'])
            ->assertStatus(402)
            ->assertJsonPath('type', 'http://localhost/problemas/pago-rechazado')
            ->assertJsonPath('detail', 'El proveedor rechazó el pago (generic_decline). Puede intentar de nuevo mientras el pedido siga pendiente.');

        $this->scopeQueriesTo($this->order->soda_id);
        $this->assertSame('rejected', PaymentModel::query()->sole()->status);
        $this->assertSame('generic_decline', PaymentModel::query()->sole()->decline_code);
        $this->assertSame('pending', $this->order->refresh()->payment_status);

        $this->postJson($this->url(), ['metodo_pago' => 'pm_card_visa'])->assertCreated();

        $this->scopeQueriesTo($this->order->soda_id);
        $this->assertSame(2, PaymentModel::query()->count());
        $this->assertSame('approved', $this->order->refresh()->payment_status);
    }

    /** Provider down: three attempts, 503 with Retry-After, and the payment left pending without a reference. */
    public function test_provider_that_does_not_answer_leaves_the_payment_pending(): void
    {
        Http::fake(['api.stripe.com/*' => Http::failedConnection()]);

        $this->postJson($this->url(), ['metodo_pago' => 'pm_card_visa'])
            ->assertServiceUnavailable()
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('type', 'http://localhost/problemas/servicio-no-disponible');

        Http::assertSentCount(3);
        $this->scopeQueriesTo($this->order->soda_id);
        $this->assertSame('pending', PaymentModel::query()->sole()->status);
        $this->assertNull(PaymentModel::query()->sole()->reference);
        $this->assertSame('pending', $this->order->refresh()->payment_status);
    }
}
