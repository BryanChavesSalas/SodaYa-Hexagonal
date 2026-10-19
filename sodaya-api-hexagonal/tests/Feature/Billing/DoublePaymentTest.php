<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Src\Billing\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Tests\Support\Billing\PaysAnOrder;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class DoublePaymentTest extends TestCase
{
    use PaysAnOrder, RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    /** A customer with a pending order; Stripe is always faked. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerWithPendingOrder();
    }

    /** Pressing pay twice while the provider is still processing: the second request answers the same payment and Stripe receives the same key. */
    public function test_a_second_request_answers_the_same_payment(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(['id' => 'pi_slow', 'status' => 'processing'], 200)]);

        $first = $this->postJson($this->url(), ['metodo_pago' => 'pm_card_visa'])->assertAccepted()->json('data.id');
        $second = $this->postJson($this->url(), ['metodo_pago' => 'pm_card_visa'])->assertAccepted()->json('data.id');

        $this->assertSame($first, $second);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', "pago-{$first}"));
        $this->scopeQueriesTo($this->order->soda_id);
        $this->assertSame(1, PaymentModel::query()->count());
    }

    /** Pressing pay twice after an approval: the second request answers 409 and the order keeps its only payment. */
    public function test_an_approved_payment_is_not_charged_again(): void
    {
        Http::fake(['api.stripe.com/*' => Http::response(self::APPROVED, 200)]);

        $this->postJson($this->url(), ['metodo_pago' => 'pm_card_visa'])->assertCreated();
        $this->postJson($this->url(), ['metodo_pago' => 'pm_card_visa'])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/pedido-ya-pagado');

        Http::assertSentCount(1);
        $this->scopeQueriesTo($this->order->soda_id);
        $this->assertSame(1, PaymentModel::query()->count());
    }
}
