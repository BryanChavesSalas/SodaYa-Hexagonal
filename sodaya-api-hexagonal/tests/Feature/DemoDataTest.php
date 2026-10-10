<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\CatalogSeeder;
use Database\Seeders\StaffSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class DemoDataTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** Load the demo data the README describes. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /** Each demo account logs in with the documented password and works on the dishes of the demo soda. */
    #[DataProvider('demoStaff')]
    public function test_demo_staff_logs_in_and_lists_the_dishes_of_the_demo_soda(string $email, array $abilities): void
    {
        $token = $this->postJson('/api/v1/tokens', [
            'correo' => $email,
            'contrasena' => StaffSeeder::PASSWORD,
            'dispositivo' => 'Computadora',
        ])->assertCreated()->assertJsonPath('data.abilities', $abilities)->json('data.token');

        $this->getJson('/api/v1/cocina/platos', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonCount(8, 'data');
    }

    /**
     * Demo accounts and the abilities of their tokens.
     *
     * @return array<string, array{string, list<string>}>
     */
    public static function demoStaff(): array
    {
        return [
            'owner' => ['duena@sodaya.test', ['cocina', 'administrar']],
            'kitchen' => ['cocina@sodaya.test', ['cocina']],
        ];
    }

    /** Seeding again keeps one account per email, both on the demo soda. */
    public function test_seeding_again_keeps_the_same_accounts(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('users', ['email' => 'duena@sodaya.test', 'role' => 'owner', 'soda_id' => CatalogSeeder::DEMO_SODA_ID]);
        $this->assertDatabaseHas('users', ['email' => 'cocina@sodaya.test', 'role' => 'kitchen', 'soda_id' => CatalogSeeder::DEMO_SODA_ID]);
    }
}
