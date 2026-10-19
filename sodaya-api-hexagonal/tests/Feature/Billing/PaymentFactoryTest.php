<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use PHPUnit\Framework\Attributes\DataProvider;
use Src\Billing\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class PaymentFactoryTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** Each state creates a payment the checks accept, for an order of the same soda and customer. */
    #[DataProvider('states')]
    public function test_each_state_creates_a_valid_payment(string $state): void
    {
        $payment = $state === 'pending' ? PaymentModel::factory()->create() : PaymentModel::factory()->{$state}()->create();
        $order = OrderModel::query()->findOrFail($payment->order_id);

        $this->assertSame($state, $payment->status);
        $this->assertSame($order->soda_id, $payment->soda_id);
        $this->assertSame($order->customer_id, $payment->customer_id);
        $this->assertSame('pago-'.$payment->id, $payment->idempotency_key);
        $this->assertSame($state !== 'pending', $payment->settled_at !== null);
    }

    /**
     * States of the factory.
     *
     * @return array<string, array{string}>
     */
    public static function states(): array
    {
        return ['pending' => ['pending'], 'approved' => ['approved'], 'rejected' => ['rejected']];
    }

    /** The payment can be created for an existing order of a customer. */
    public function test_payment_can_be_created_for_an_existing_order(): void
    {
        $order = OrderModel::factory()->create();

        $payment = PaymentModel::factory()->approved()->create([
            'order_id' => $order->id,
            'soda_id' => $order->soda_id,
            'customer_id' => $order->customer_id,
        ]);

        $this->assertSame(1, OrderModel::query()->count());
        $this->assertSame($order->id, $payment->order_id);
    }
}
