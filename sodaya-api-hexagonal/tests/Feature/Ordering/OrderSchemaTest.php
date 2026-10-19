<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class OrderSchemaTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private string $sodaId;

    private string $customerId;

    private string $dishId;

    private int $pickupNumber = 0;

    /** Give a soda a customer and a dish, under that soda. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->customerId = $this->insertCustomer();
        $this->sodaId = $this->insertSoda();
        $this->dishId = $this->insertDish($this->sodaId);
    }

    /** An order with its line is stored with UUID v7 identifiers and starts pending. */
    public function test_order_and_line_are_stored(): void
    {
        $orderId = $this->insertOrder();
        $this->insertLine($orderId, $this->dishId, ['quantity' => 2]);

        $order = DB::table('orders')->sole();
        $line = DB::table('order_lines')->sole();

        $this->assertTrue(Str::isUuid($order->id, 7));
        $this->assertTrue(Str::isUuid($line->id, 7));
        $this->assertSame('pending', $order->status);
        $this->assertSame('Casado de pollo', $line->dish_name);
        $this->assertSame(2_800, $line->unit_price);
    }

    /** The quantity of a line goes from 1 to 20. */
    #[DataProvider('quantities')]
    public function test_quantity_is_between_one_and_twenty(int $quantity, bool $valid): void
    {
        $orderId = $this->insertOrder();

        if (! $valid) {
            $this->expectException(QueryException::class);
            $this->expectExceptionCode('23514');
        }

        $this->insertLine($orderId, $this->dishId, ['quantity' => $quantity]);

        $this->assertSame($quantity, DB::table('order_lines')->value('quantity'));
    }

    /**
     * Quantities at and beyond the limits.
     *
     * @return array<string, array{int, bool}>
     */
    public static function quantities(): array
    {
        return [
            'zero' => [0, false],
            'one' => [1, true],
            'twenty' => [20, true],
            'twenty-one' => [21, false],
        ];
    }

    /** The unit price keeps the range of the catalog. */
    public function test_unit_price_keeps_the_range_of_the_catalog(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23514');

        $this->insertLine($this->insertOrder(), $this->dishId, ['unit_price' => 99]);
    }

    /** A line cannot point to a dish of another soda, even if the identifier exists. */
    public function test_line_cannot_use_a_dish_of_another_soda(): void
    {
        $foreignDishId = $this->insertDish($this->insertSoda());
        $orderId = $this->insertOrder();

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23503');

        $this->insertLine($orderId, $foreignDishId);
    }

    /** A customer uses a key once; another customer may use the same key, and the fingerprint always goes with the key. */
    public function test_idempotency_key_is_unique_per_customer(): void
    {
        $fingerprint = str_repeat('a', 64);
        $this->insertOrder(['idempotency_key' => 'toque-1', 'request_fingerprint' => $fingerprint]);
        $this->insertOrder(['customer_id' => $this->insertCustomer(), 'idempotency_key' => 'toque-1', 'request_fingerprint' => $fingerprint]);

        $this->assertSame(2, DB::table('orders')->where('idempotency_key', 'toque-1')->count());

        try {
            DB::transaction(fn (): string => $this->insertOrder(['idempotency_key' => 'toque-2']));
            $this->fail('A key was stored without its fingerprint.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23505');

        $this->insertOrder(['idempotency_key' => 'toque-1', 'request_fingerprint' => $fingerprint]);
    }

    /** A pickup number is used once per soda and day: another day or another soda may repeat it. */
    public function test_pickup_number_is_unique_per_soda_and_day(): void
    {
        $this->insertOrder(['pickup_date' => '2026-10-05', 'pickup_number' => 7]);
        $this->insertOrder(['pickup_date' => '2026-10-06', 'pickup_number' => 7]);

        $otherSodaId = $this->insertSoda();
        $this->scopeQueriesTo($otherSodaId);
        DB::table('orders')->insert(['soda_id' => $otherSodaId, 'customer_id' => $this->customerId, 'pickup_date' => '2026-10-05', 'pickup_number' => 7]);

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23505');

        $this->insertOrder(['pickup_date' => '2026-10-05', 'pickup_number' => 7]);
    }

    /** A pickup number goes from 1 to 9999 and the counter of a soda and day is one row. */
    public function test_pickup_number_and_counter_keep_their_rules(): void
    {
        DB::table('pickup_counters')->insert(['soda_id' => $this->sodaId, 'pickup_date' => '2026-10-05', 'last_number' => 3]);

        try {
            DB::transaction(fn (): bool => DB::table('pickup_counters')->insert(['soda_id' => $this->sodaId, 'pickup_date' => '2026-10-05', 'last_number' => 4]));
            $this->fail('A soda got two counters for the same day.');
        } catch (QueryException $exception) {
            $this->assertSame('23505', $exception->getCode());
        }

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23514');

        $this->insertOrder(['pickup_number' => 10_000]);
    }

    /** The preparation minutes of a line keep the range of the catalog. */
    #[DataProvider('preparationMinutes')]
    public function test_preparation_minutes_keep_the_range_of_the_catalog(int $minutes, bool $valid): void
    {
        $orderId = $this->insertOrder();

        if (! $valid) {
            $this->expectException(QueryException::class);
            $this->expectExceptionCode('23514');
        }

        $this->insertLine($orderId, $this->dishId, ['preparation_minutes' => $minutes]);

        $this->assertSame($minutes, DB::table('order_lines')->value('preparation_minutes'));
    }

    /**
     * Minutes at and beyond the limits.
     *
     * @return array<string, array{int, bool}>
     */
    public static function preparationMinutes(): array
    {
        return [
            'zero' => [0, false],
            'one' => [1, true],
            'two hours' => [120, true],
            'beyond two hours' => [121, false],
        ];
    }

    /** A dish appears once per order. */
    public function test_dish_appears_once_per_order(): void
    {
        $orderId = $this->insertOrder();
        $this->insertLine($orderId, $this->dishId);

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23505');

        $this->insertLine($orderId, $this->dishId);
    }

    /** The status only accepts the five values of the domain. */
    #[DataProvider('statuses')]
    public function test_status_accepts_only_the_five_values(string $status, bool $valid): void
    {
        if (! $valid) {
            $this->expectException(QueryException::class);
            $this->expectExceptionCode('23514');
        }

        $this->insertOrder(['status' => $status, ...$status === 'cancelled' ? ['cancellation_origin' => 'kitchen', 'cancelled_at' => now()] : []]);

        $this->assertSame($status, DB::table('orders')->value('status'));
    }

    /**
     * The five valid statuses and a few that are not.
     *
     * @return array<string, array{string, bool}>
     */
    public static function statuses(): array
    {
        return [
            'pending' => ['pending', true],
            'confirmed' => ['confirmed', true],
            'ready' => ['ready', true],
            'delivered' => ['delivered', true],
            'cancelled' => ['cancelled', true],
            'paid' => ['paid', false],
            'spanish value' => ['pendiente', false],
            'uppercase' => ['PENDING', false],
        ];
    }

    /** A cancelled order always has its origin and its moment, and only a cancelled order has them. */
    #[DataProvider('cancellations')]
    public function test_cancellation_data_matches_the_status(string $status, ?string $origin, bool $withMoment): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23514');

        $this->insertOrder(['status' => $status, 'cancellation_origin' => $origin, 'cancelled_at' => $withMoment ? now() : null]);
    }

    /**
     * Combinations that break the cancellation rule.
     *
     * @return array<string, array{string, string|null, bool}>
     */
    public static function cancellations(): array
    {
        return [
            'cancelled without origin' => ['cancelled', null, true],
            'cancelled without moment' => ['cancelled', 'kitchen', false],
            'pending with an origin' => ['pending', 'kitchen', true],
            'unknown origin' => ['cancelled', 'robot', true],
        ];
    }

    /** An approved payment always has its moment, and a pending one never has it. */
    #[DataProvider('payments')]
    public function test_payment_moment_matches_its_status(string $status, bool $withMoment, bool $valid): void
    {
        if (! $valid) {
            $this->expectException(QueryException::class);
            $this->expectExceptionCode('23514');
        }

        $this->insertOrder(['payment_status' => $status, 'paid_at' => $withMoment ? now() : null]);

        $this->assertSame($status, DB::table('orders')->value('payment_status'));
    }

    /**
     * Payment statuses with and without their moment.
     *
     * @return array<string, array{string, bool, bool}>
     */
    public static function payments(): array
    {
        return [
            'pending without moment' => ['pending', false, true],
            'approved with moment' => ['approved', true, true],
            'approved without moment' => ['approved', false, false],
            'pending with moment' => ['pending', true, false],
            'unknown status' => ['refunded', true, false],
        ];
    }

    /** Deleting an order deletes its lines. */
    public function test_deleting_an_order_deletes_its_lines(): void
    {
        $orderId = $this->insertOrder();
        $this->insertLine($orderId, $this->dishId);

        DB::table('orders')->where('id', $orderId)->delete();

        $this->assertSame(0, DB::table('order_lines')->count());
    }

    /** A dish that was ordered cannot be deleted: the restriction keeps the history of the orders. */
    public function test_ordered_dish_cannot_be_deleted(): void
    {
        $this->insertLine($this->insertOrder(), $this->dishId);

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23001');

        DB::table('dishes')->where('id', $this->dishId)->delete();
    }

    /** Insert a customer account, which belongs to no soda. */
    private function insertCustomer(): string
    {
        $id = (string) Str::uuid7();
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Clienta',
            'email' => "{$id}@sodaya.test",
            'password' => 'x',
            'role' => 'customer',
        ]);

        return $id;
    }

    /** Insert a soda and return its identifier. */
    private function insertSoda(): string
    {
        $id = (string) Str::uuid7();
        DB::table('sodas')->insert(['id' => $id, 'name' => 'Soda de prueba']);

        return $id;
    }

    /** Insert a dish for the soda, under that soda, and return its identifier. */
    private function insertDish(string $sodaId): string
    {
        $id = (string) Str::uuid7();
        $this->scopeQueriesTo($sodaId);
        DB::table('dishes')->insert([
            'id' => $id,
            'soda_id' => $sodaId,
            'name' => 'Casado de pollo',
            'price' => 2_800,
            'preparation_minutes' => 15,
            'available_portions' => 10,
        ]);

        return $id;
    }

    /**
     * Insert an order of the soda, under that soda, and return its identifier.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function insertOrder(array $overrides = []): string
    {
        $id = (string) Str::uuid7();
        $this->scopeQueriesTo($this->sodaId);
        DB::table('orders')->insert([
            'id' => $id,
            'soda_id' => $this->sodaId,
            'customer_id' => $this->customerId,
            'pickup_date' => '2026-10-05',
            'pickup_number' => ++$this->pickupNumber,
            ...$overrides,
        ]);

        return $id;
    }

    /**
     * Insert a line of an order of the soda, under that soda.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function insertLine(string $orderId, string $dishId, array $overrides = []): void
    {
        $this->scopeQueriesTo($this->sodaId);
        DB::table('order_lines')->insert([
            'order_id' => $orderId,
            'soda_id' => $this->sodaId,
            'dish_id' => $dishId,
            'dish_name' => 'Casado de pollo',
            'unit_price' => 2_800,
            'quantity' => 1,
            'preparation_minutes' => 15,
            ...$overrides,
        ]);
    }
}
