<?php

declare(strict_types=1);

namespace Tests\Support\Billing;

use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;

trait PaysAnOrder
{
    /** What Stripe answers for an approved charge, in the shape of a payment intent. */
    private const array APPROVED = ['id' => 'pi_3SodaYa', 'status' => 'succeeded'];

    /** What Stripe answers for a declined card: 402 with the error and the intent it created. */
    private const array DECLINED = [
        'error' => [
            'type' => 'card_error',
            'code' => 'card_declined',
            'decline_code' => 'generic_decline',
            'payment_intent' => ['id' => 'pi_declined', 'status' => 'requires_payment_method'],
        ],
    ];

    private UserModel $customer;

    private OrderModel $order;

    /** A customer authenticated with the abilities of the role, with a pending order of ₡6600 at a soda, at noon of a Monday. */
    private function prepareCustomerWithPendingOrder(): void
    {
        $this->travelTo('2026-10-05 12:00:00');
        $this->customer = UserModel::factory()->create();
        $this->order = OrderModel::factory()->create(['customer_id' => $this->customer->id]);
        OrderLineModel::factory()->for($this->order, 'order')->create(['unit_price' => 2_800, 'quantity' => 2]);
        OrderLineModel::factory()->for($this->order, 'order')->create(['unit_price' => 1_000, 'quantity' => 1]);
        Sanctum::actingAs($this->customer, Role::Customer->abilities());
    }

    /** The URL of the payment of an order. */
    private function url(?string $orderId = null): string
    {
        return '/api/v1/pedidos/'.($orderId ?? $this->order->id).'/pago';
    }
}
