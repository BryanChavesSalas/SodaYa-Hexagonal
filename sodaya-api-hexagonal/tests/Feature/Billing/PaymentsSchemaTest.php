<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class PaymentsSchemaTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private OrderModel $order;

    /** A pending order of a customer at a soda, with the transaction scoped to that soda. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->order = OrderModel::factory()->create();
        $this->scopeQueriesTo($this->order->soda_id);
    }

    /** An order has at most one pending or approved payment: the partial unique index refuses the second. */
    #[DataProvider('livePairs')]
    public function test_an_order_has_at_most_one_live_payment(string $first, string $second): void
    {
        $this->insert($this->outcome($first));

        $this->assertSqlState('23505', fn () => $this->insert($this->outcome($second)));
        $this->assertStringContainsString(
            'WHERE ((status)::text = ANY',
            (string) DB::scalar("select indexdef from pg_indexes where indexname = 'payments_live_order_unique'"),
        );
    }

    /**
     * Pairs of payments that cannot coexist for one order.
     *
     * @return array<string, array{string, string}>
     */
    public static function livePairs(): array
    {
        return [
            'two pending' => ['pending', 'pending'],
            'approved then pending' => ['approved', 'pending'],
            'pending then approved' => ['pending', 'approved'],
        ];
    }

    /** A rejected payment leaves room for another attempt: the index only covers the live ones. */
    public function test_a_rejected_payment_leaves_room_for_another_attempt(): void
    {
        $this->insert($this->outcome('rejected'));
        $this->insert($this->outcome('rejected'));
        $this->insert($this->outcome('pending'));

        $this->assertSame(3, DB::table('payments')->where('order_id', $this->order->id)->count());
    }

    /** The amount, the statuses and the columns of the outcome are checked by the database. */
    #[DataProvider('invalidPayments')]
    public function test_invalid_payments_are_rejected(array $columns): void
    {
        $this->assertSqlState('23514', fn () => $this->insert($columns));
    }

    /**
     * Payments that break a CHECK constraint.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPayments(): array
    {
        return [
            'amount of zero' => [['amount' => 0]],
            'negative amount' => [['amount' => -5_600]],
            'unknown status' => [['status' => 'refunded', 'settled_at' => '2026-10-05 12:00:00-06']],
            'approved without a moment' => [['status' => 'approved', 'reference' => 'pi_x']],
            'pending with a moment' => [['status' => 'pending', 'settled_at' => '2026-10-05 12:00:00-06']],
            'approved with a decline code' => [['status' => 'approved', 'reference' => 'pi_x', 'decline_code' => 'generic_decline', 'settled_at' => '2026-10-05 12:00:00-06']],
        ];
    }

    /** A payment cannot point to an order of another soda, even with a valid identifier. */
    public function test_a_payment_cannot_point_to_an_order_of_another_soda(): void
    {
        $foreignOrder = OrderModel::factory()->create();
        $this->scopeQueriesTo($this->order->soda_id);

        $this->assertSqlState('23503', fn () => $this->insert(['order_id' => $foreignOrder->id]));
    }

    /** The key sent to the provider is unique: two attempts never share it. */
    public function test_the_idempotency_key_is_unique(): void
    {
        $otherOrder = OrderModel::factory()->create(['soda_id' => $this->order->soda_id, 'customer_id' => $this->order->customer_id]);
        $this->insert(['idempotency_key' => 'pago-repetida']);

        $this->assertSqlState('23505', fn () => $this->insert(['order_id' => $otherOrder->id, 'idempotency_key' => 'pago-repetida']));
    }

    /** Only the transaction of the soda reaches its payments. */
    public function test_payments_are_only_visible_to_their_soda(): void
    {
        $this->insert();

        $this->scopeQueriesTo(null);
        $this->assertSame(0, DB::table('payments')->count());

        $this->scopeQueriesTo((string) Str::uuid7());
        $this->assertSame(0, DB::table('payments')->count());

        $this->scopeQueriesTo($this->order->soda_id);
        $this->assertSame(1, DB::table('payments')->count());
    }

    /**
     * Insert a payment of the order of the scenario, with any column overridden.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function insert(array $overrides = []): void
    {
        DB::table('payments')->insert([
            'order_id' => $this->order->id,
            'soda_id' => $this->order->soda_id,
            'customer_id' => $this->order->customer_id,
            'provider' => 'stripe',
            'amount' => 5_600,
            'status' => 'pending',
            'idempotency_key' => 'pago-'.Str::uuid7(),
            ...$overrides,
        ]);
    }

    /**
     * Columns of a payment in a status, consistent with the checks.
     *
     * @return array<string, mixed>
     */
    private function outcome(string $status): array
    {
        return match ($status) {
            'pending' => [],
            'approved' => ['status' => 'approved', 'reference' => 'pi_'.Str::random(10), 'settled_at' => '2026-10-05 12:00:00-06'],
            'rejected' => ['status' => 'rejected', 'reference' => 'pi_'.Str::random(10), 'decline_code' => 'generic_decline', 'settled_at' => '2026-10-05 12:00:00-06'],
        };
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
