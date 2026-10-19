<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class PointMovementsSchemaTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private string $customerId;

    private string $orderId;

    /** A customer with a delivered order. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->customerId = UserModel::factory()->create()->id;
        $this->orderId = OrderModel::factory()->delivered()->create(['customer_id' => $this->customerId])->id;
    }

    /** The balance is not stored: it is the sum of the movements. */
    public function test_balance_is_the_sum_of_the_movements(): void
    {
        $this->movement('purchase', 5, $this->orderId);
        $this->movement('redemption', -3);

        $this->assertSame(2, (int) DB::table('point_movements')->where('customer_id', $this->customerId)->sum('points'));
        $this->assertNotContains('balance', DB::getSchemaBuilder()->getColumnListing('point_movements'));
        $this->assertNotContains('points', DB::getSchemaBuilder()->getColumnListing('users'));
    }

    /** An order earns points once: a second purchase for it violates the partial unique index. */
    public function test_an_order_earns_points_once(): void
    {
        $this->movement('purchase', 5, $this->orderId);

        $this->assertSqlState('23505', fn () => $this->movement('purchase', 5, $this->orderId));
    }

    /** The partial index only covers purchases: an order can still pay with points more than once. */
    public function test_index_only_covers_purchases(): void
    {
        $this->movement('purchase', 5, $this->orderId);
        $this->movement('redemption', -1, $this->orderId);
        $this->movement('redemption', -1, $this->orderId);

        $this->assertSame(3, DB::table('point_movements')->count());
    }

    /** The reason and the sign of the points must match. */
    #[DataProvider('invalidMovements')]
    public function test_invalid_movements_are_rejected(string $reason, int $points, bool $withOrder): void
    {
        $this->assertSqlState('23514', fn () => $this->movement($reason, $points, $withOrder ? $this->orderId : null));
    }

    /**
     * Movements that break a CHECK constraint.
     *
     * @return array<string, array{string, int, bool}>
     */
    public static function invalidMovements(): array
    {
        return [
            'unknown reason' => ['gift', 5, true],
            'purchase without points' => ['purchase', 0, true],
            'purchase that takes points' => ['purchase', -5, true],
            'purchase without an order' => ['purchase', 5, false],
            'redemption that adds points' => ['redemption', 5, false],
        ];
    }

    /** The application role can neither change nor delete a movement. */
    #[DataProvider('forbiddenChanges')]
    public function test_application_role_cannot_change_movements(string $sql): void
    {
        $this->movement('purchase', 5, $this->orderId);

        $this->assertSqlState('42501', fn () => DB::statement($sql));
    }

    /**
     * Statements the application role is not allowed to run.
     *
     * @return array<string, array{string}>
     */
    public static function forbiddenChanges(): array
    {
        return [
            'update' => ['update point_movements set points = 500'],
            'delete' => ['delete from point_movements'],
            'truncate' => ['truncate point_movements'],
        ];
    }

    /** Even the owner of the table, who has every privilege, is stopped by the trigger. */
    public function test_trigger_rejects_changes_even_for_the_owner(): void
    {
        $owner = DB::connection('pgsql_admin');
        $owner->beginTransaction();

        try {
            $customerId = $owner->table('users')->insertGetId([
                'name' => 'Ana Mora', 'email' => 'ana@sodaya.test', 'password' => 'x', 'role' => 'customer',
            ]);
            $owner->table('point_movements')->insert(['customer_id' => $customerId, 'reason' => 'redemption', 'points' => -1]);

            foreach (['update point_movements set points = -2', 'delete from point_movements'] as $sql) {
                try {
                    $owner->transaction(fn () => $owner->statement($sql));
                    $this->fail('The owner changed a point movement.');
                } catch (QueryException $exception) {
                    $this->assertSame('P0001', $exception->getCode());
                    $this->assertStringContainsString('Point movements cannot be changed or deleted', $exception->getMessage());
                }
            }
        } finally {
            $owner->rollBack();
        }
    }

    /** Insert a movement of the customer. */
    private function movement(string $reason, int $points, ?string $orderId = null): void
    {
        DB::table('point_movements')->insert([
            'customer_id' => $this->customerId,
            'order_id' => $orderId,
            'reason' => $reason,
            'points' => $points,
        ]);
    }

    /** Run a statement inside a savepoint and assert the SQLSTATE of its failure. */
    private function assertSqlState(string $state, callable $statement): void
    {
        try {
            DB::transaction(fn () => $statement());
            $this->fail("The statement did not fail with {$state}.");
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->getCode());
        }
    }
}
