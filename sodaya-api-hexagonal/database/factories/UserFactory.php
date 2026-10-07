<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Src\Identity\Domain\Enums\Role;
use Src\Identity\Infrastructure\Persistence\Models\UserModel;

/**
 * @extends Factory<UserModel>
 */
final class UserFactory extends Factory
{
    protected $model = UserModel::class;

    protected static ?string $password;

    /**
     * Default state: an active customer with no soda.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'soda_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'role' => Role::Customer,
            'is_active' => true,
        ];
    }

    /** Kitchen staff of the given soda. */
    public function kitchen(string $sodaId): static
    {
        return $this->state(['role' => Role::Kitchen, 'soda_id' => $sodaId]);
    }

    /** Owner of the given soda. */
    public function owner(string $sodaId): static
    {
        return $this->state(['role' => Role::Owner, 'soda_id' => $sodaId]);
    }

    /** A deactivated account. */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
