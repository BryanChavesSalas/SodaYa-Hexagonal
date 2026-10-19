<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfASoda;
use Illuminate\Database\Eloquent\Factories\Factory;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

/**
 * @extends Factory<OrderModel>
 */
final class OrderFactory extends Factory
{
    use CreatesRowsOfASoda;

    protected $model = OrderModel::class;

    /**
     * Default state of a fictitious pending order of a customer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'soda_id' => SodaModel::factory(),
            'customer_id' => UserModel::factory(),
            'status' => 'pending',
        ];
    }

    /** Leave the payment of the order approved. */
    public function paid(): static
    {
        return $this->state(fn (): array => ['payment_status' => 'approved', 'paid_at' => now()]);
    }

    /** Leave the order paid and confirmed by the kitchen. */
    public function confirmed(): static
    {
        return $this->paid()->state(fn (): array => ['status' => 'confirmed', 'confirmed_at' => now()]);
    }

    /** Leave the order paid and ready to be picked up. */
    public function ready(): static
    {
        return $this->paid()->state(fn (): array => ['status' => 'ready', 'confirmed_at' => now(), 'ready_at' => now()]);
    }

    /** Leave the order paid and delivered to its customer. */
    public function delivered(): static
    {
        return $this->paid()->state(fn (): array => [
            'status' => 'delivered',
            'confirmed_at' => now(),
            'ready_at' => now(),
            'delivered_at' => now(),
        ]);
    }

    /** Leave the order cancelled, by the kitchen unless another origin is given. */
    public function cancelled(string $origin = 'kitchen'): static
    {
        return $this->state(fn (): array => ['status' => 'cancelled', 'cancellation_origin' => $origin, 'cancelled_at' => now()]);
    }
}
