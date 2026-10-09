<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;

/**
 * @extends Factory<UserModel>
 */
final class UserFactory extends Factory
{
    public const string PASSWORD = 'password';

    protected $model = UserModel::class;

    private static ?string $passwordHash = null;

    /**
     * Default state of a fictitious active customer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$passwordHash ??= Hash::make(self::PASSWORD),
            'role' => Role::Customer->value,
            'soda_id' => null,
            'is_active' => true,
        ];
    }

    /** Make the account the kitchen of a soda. */
    public function kitchen(): static
    {
        return $this->state(['role' => Role::Kitchen->value, 'soda_id' => SodaModel::factory()]);
    }

    /** Make the account the owner of a soda. */
    public function owner(): static
    {
        return $this->state(['role' => Role::Owner->value, 'soda_id' => SodaModel::factory()]);
    }

    /** Deactivate the account. */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
