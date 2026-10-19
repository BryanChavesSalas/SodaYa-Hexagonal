<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Concerns\CreatesRowsOfASoda;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Src\Billing\Payments\Infrastructure\Persistence\Models\PaymentModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

/**
 * @extends Factory<PaymentModel>
 */
final class PaymentFactory extends Factory
{
    use CreatesRowsOfASoda;

    protected $model = PaymentModel::class;

    /**
     * Default state of a pending attempt to pay a new pending order of a customer, with the key derived from its identity.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) Str::uuid7();

        return [
            'id' => $id,
            'soda_id' => SodaModel::factory(),
            'customer_id' => UserModel::factory(),
            'order_id' => fn (array $attributes): string => OrderModel::factory()->create([
                'soda_id' => $attributes['soda_id'],
                'customer_id' => $attributes['customer_id'],
            ])->id,
            'provider' => 'stripe',
            'amount' => 5_600,
            'status' => 'pending',
            'idempotency_key' => 'pago-'.$id,
        ];
    }

    /** Leave the payment approved by the provider, with a reference of its own. */
    public function approved(): static
    {
        return $this->state(fn (): array => ['status' => 'approved', 'reference' => $this->reference(), 'settled_at' => now()]);
    }

    /** Leave the payment declined by the bank, with the code it gave. */
    public function rejected(string $declineCode = 'generic_decline'): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'reference' => $this->reference(),
            'decline_code' => $declineCode,
            'settled_at' => now(),
        ]);
    }

    /** A fictitious reference of the provider, unique in the run. */
    private function reference(): string
    {
        return 'pi_'.fake()->unique()->regexify('[A-Za-z0-9]{24}');
    }
}
