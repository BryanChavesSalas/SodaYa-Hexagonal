<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Src\Billing\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\Billing\PaysAnOrder;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class CustomerPaysOrderTest extends TestCase
{
    use PaysAnOrder, RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    /** A customer with a pending order; Stripe is always faked. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerWithPendingOrder();
    }

    /** Approved: 201 with the payment, the order paid, and the exact amount in céntimos sent to Stripe with the key and the test secret; the amount of the body is ignored. */
    public function test_approved_charge_answers_created_and_leaves_the_order_paid(): void
    {
        Http::fake(['api.stripe.com/v1/payment_intents' => Http::response(self::APPROVED, 200)]);

        $this->postJson($this->url(), ['metodo_pago' => 'pm_card_visa', 'monto' => 1])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'aprobado')
            ->assertJsonPath('data.monto', 6_600)
            ->assertJsonPath('data.proveedor', 'stripe')
            ->assertJsonPath('data.referencia', 'pi_3SodaYa')
            ->assertJsonPath('data.pedido_id', $this->order->id)
            ->assertJsonPath('data.cerrado_en', '2026-10-05T12:00:00-06:00');

        Http::assertSent(fn (Request $request): bool => $request['amount'] === 660_000
            && $request['currency'] === 'crc'
            && $request['payment_method'] === 'pm_card_visa'
            && $request->hasHeader('Idempotency-Key', 'pago-'.PaymentModel::query()->sole()->id)
            && $request->hasHeader('Authorization', 'Bearer sk_test_sodaya'));
        $this->scopeQueriesTo($this->order->soda_id);
        $this->assertSame('approved', $this->order->refresh()->payment_status);
        $this->assertSame('approved', PaymentModel::query()->sole()->status);
    }

    /** An order already paid answers 409 pedido-ya-pagado without touching Stripe. */
    public function test_an_order_already_paid_is_refused(): void
    {
        Http::fake();
        $paid = OrderModel::factory()->paid()->create(['soda_id' => $this->order->soda_id, 'customer_id' => $this->customer->id]);

        $this->postJson($this->url($paid->id), ['metodo_pago' => 'pm_card_visa'])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/pedido-ya-pagado');

        Http::assertNothingSent();
    }

    /** An order that is no longer pending answers 409 pedido-no-pagable without touching Stripe. */
    public function test_an_order_that_is_not_pending_is_refused(): void
    {
        Http::fake();
        $cancelled = OrderModel::factory()->cancelled()->create(['soda_id' => $this->order->soda_id, 'customer_id' => $this->customer->id]);

        $this->postJson($this->url($cancelled->id), ['metodo_pago' => 'pm_card_visa'])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/pedido-no-pagable');

        Http::assertNothingSent();
    }

    /** An order of another customer answers 404, like one that does not exist. */
    public function test_an_order_of_another_customer_is_not_found(): void
    {
        Http::fake();
        $other = OrderModel::factory()->create(['soda_id' => $this->order->soda_id]);

        $this->postJson($this->url($other->id), ['metodo_pago' => 'pm_card_visa'])
            ->assertNotFound()
            ->assertJsonPath('type', 'http://localhost/problemas/pedido-no-encontrado');

        Http::assertNothingSent();
    }

    /** Only the payment method travels, with the shape of a payment method of Stripe. */
    public function test_payment_method_is_required(): void
    {
        Http::fake();

        $this->postJson($this->url(), ['monto' => 6_600])
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['metodo_pago']]);

        Http::assertNothingSent();
    }
}
