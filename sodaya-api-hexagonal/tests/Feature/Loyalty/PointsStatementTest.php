<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class PointsStatementTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/puntos';

    private UserModel $ana;

    private UserModel $luis;

    /** Two customers: Ana earned 30 points in 25 orders, one per minute, and spent 4; Luis earned 50. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->ana = $this->customer();
        $this->luis = $this->customer();

        for ($minute = 0; $minute < 25; $minute++) {
            $this->movement($this->ana, $minute === 0 ? 6 : 1, "2026-10-05 12:{$this->two($minute)}:00");
        }

        $this->movement($this->ana, -4, '2026-10-05 13:00:00', reason: 'redemption');
        $this->movement($this->luis, 50, '2026-10-05 12:30:00');
    }

    /** The customer sees the balance and the latest 20 movements, newest first. */
    public function test_customer_sees_the_balance_and_the_latest_twenty_movements(): void
    {
        Sanctum::actingAs($this->ana, Role::Customer->abilities());

        $response = $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.saldo', 26)
            ->assertJsonCount(20, 'data.movimientos')
            ->assertJsonPath('data.movimientos.0.motivo', 'canje')
            ->assertJsonPath('data.movimientos.0.puntos', -4)
            ->assertJsonPath('data.movimientos.1.motivo', 'compra')
            ->assertJsonPath('data.movimientos.1.creado_en', '2026-10-05T12:24:00-06:00');

        $this->assertSame('2026-10-05T12:06:00-06:00', $response->json('data.movimientos.19.creado_en'));
    }

    /** A customer never sees the points of another: the balance and the list are only theirs. */
    public function test_customer_cannot_see_the_points_of_another(): void
    {
        Sanctum::actingAs($this->luis, Role::Customer->abilities());

        $this->getJson(self::ENDPOINT.'?cliente='.$this->ana->id)
            ->assertOk()
            ->assertJsonPath('data.saldo', 50)
            ->assertJsonCount(1, 'data.movimientos');
    }

    /** A new customer has a balance of zero and no movements. */
    public function test_new_customer_has_no_points(): void
    {
        Sanctum::actingAs($this->customer(), Role::Customer->abilities());

        $this->getJson(self::ENDPOINT)->assertOk()->assertExactJson(['data' => ['saldo' => 0, 'movimientos' => []]]);
    }

    /** The staff has no points and a request without a token is not authenticated. */
    public function test_only_customers_have_points(): void
    {
        Sanctum::actingAs(UserModel::factory()->create(), Role::Kitchen->abilities());
        $this->getJson(self::ENDPOINT)->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson(self::ENDPOINT, ['Authorization' => ''])->assertUnauthorized();
    }

    /** Create a customer account. */
    private function customer(): UserModel
    {
        return UserModel::factory()->create(['role' => Role::Customer->value, 'soda_id' => null]);
    }

    /** Add a movement for a customer at a moment; a purchase gets its own delivered order. */
    private function movement(UserModel $customer, int $points, string $at, string $reason = 'purchase'): void
    {
        DB::table('point_movements')->insert([
            'customer_id' => $customer->id,
            'order_id' => $reason === 'purchase' ? OrderModel::factory()->delivered()->create(['customer_id' => $customer->id])->id : null,
            'reason' => $reason,
            'points' => $points,
            'created_at' => $at,
        ]);
    }

    /** A number with two digits. */
    private function two(int $number): string
    {
        return str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    }
}
