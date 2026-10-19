<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class RedeemPointsTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/puntos/canjes';

    private UserModel $customer;

    /** A customer with 10 points, authenticated with the abilities of the role. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->customer = UserModel::factory()->create(['role' => Role::Customer->value, 'soda_id' => null]);
        DB::table('point_movements')->insert([
            'customer_id' => $this->customer->id,
            'order_id' => OrderModel::factory()->delivered()->create(['customer_id' => $this->customer->id])->id,
            'reason' => 'purchase',
            'points' => 10,
        ]);
        Sanctum::actingAs($this->customer, Role::Customer->abilities());
    }

    /** A redemption within the balance answers 201 with the movement and the balance left. */
    public function test_customer_redeems_points(): void
    {
        $this->postJson(self::ENDPOINT, ['puntos' => 3])
            ->assertCreated()
            ->assertJsonPath('data.motivo', 'canje')
            ->assertJsonPath('data.puntos', -3)
            ->assertJsonPath('data.creado_en', now()->format(DATE_ATOM))
            ->assertJsonPath('saldo', 7);

        $this->assertSame(7, (int) DB::table('point_movements')->where('customer_id', $this->customer->id)->sum('points'));
    }

    /** A redemption above the balance answers 409 puntos-insuficientes and writes nothing. */
    public function test_redemption_above_the_balance_is_refused(): void
    {
        $this->postJson(self::ENDPOINT, ['puntos' => 11])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/puntos-insuficientes')
            ->assertJsonPath('detail', 'El saldo no alcanza: tiene 10 puntos.');

        $this->assertSame(1, DB::table('point_movements')->count());
    }

    /** The amount is a positive whole number. */
    #[DataProvider('invalidAmounts')]
    public function test_invalid_amount_is_rejected(mixed $points): void
    {
        $this->postJson(self::ENDPOINT, ['puntos' => $points])
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['puntos']]);
    }

    /**
     * Amounts the endpoint rejects.
     *
     * @return array<string, array{mixed}>
     */
    public static function invalidAmounts(): array
    {
        return ['zero' => [0], 'negative' => [-5], 'decimal' => [2.5], 'missing' => [null]];
    }

    /** The staff of a soda has no points to redeem: their token lacks the ability. */
    public function test_staff_cannot_redeem(): void
    {
        $owner = UserModel::factory()->create();
        Sanctum::actingAs($owner, Role::Owner->abilities());

        $this->postJson(self::ENDPOINT, ['puntos' => 1])->assertForbidden();
    }

    /** Without a token the endpoint answers 401. */
    public function test_redemption_needs_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson(self::ENDPOINT, ['puntos' => 1])->assertUnauthorized();
    }
}
