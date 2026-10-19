<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\SodaTransaction;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class CustomerOrderAccessTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private SodaModel $soda;

    private UserModel $customer;

    private UserModel $otherCustomer;

    private OrderModel $own;

    private OrderModel $elsewhere;

    private OrderModel $foreign;

    /**
     * A customer with an order at a soda, confirmed so it has history, and another at a second soda;
     * another customer with an order at the first soda. Each order has one line.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->soda = SodaModel::factory()->create();
        $otherSoda = SodaModel::factory()->create();
        $this->customer = UserModel::factory()->create();
        $this->otherCustomer = UserModel::factory()->create();

        $this->own = $this->orderOf($this->customer, $this->soda);
        $this->elsewhere = $this->orderOf($this->customer, $otherSoda);
        $this->foreign = $this->orderOf($this->otherCustomer, $this->soda);

        $this->scopeQueriesTo($this->soda->id);
        DB::update("update orders set status = 'confirmed', confirmed_at = now() where id = ?", [$this->own->id]);
        $this->scopeQueriesTo(null);
    }

    /** With only the customer set, the queries reach the orders of that customer at every soda, with their lines and history, and nobody else's. */
    public function test_customer_reads_their_own_orders_at_every_soda(): void
    {
        $this->scopeQueriesToCustomer($this->customer->id);

        $this->assertSame([$this->own->id, $this->elsewhere->id], DB::table('orders')->orderBy('id')->pluck('id')->all());
        $this->assertSame(2, DB::table('order_lines')->count());
        $this->assertSame([$this->own->id], DB::table('order_status_history')->pluck('order_id')->all());

        $this->scopeQueriesToCustomer($this->otherCustomer->id);

        $this->assertSame([$this->foreign->id], DB::table('orders')->pluck('id')->all());
        $this->assertSame(1, DB::table('order_lines')->count());
        $this->assertSame(0, DB::table('order_status_history')->count());
    }

    /** Without a soda and without a customer, no order, line or history row is visible. */
    public function test_without_customer_or_soda_no_order_is_visible(): void
    {
        $this->scopeQueriesToCustomer(null);

        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(0, DB::table('order_lines')->count());
        $this->assertSame(0, DB::table('order_status_history')->count());
    }

    /** The customer only reads: without the soda of the order, an update or a delete reaches no row and an insert is refused. */
    public function test_customer_scope_reads_but_never_writes(): void
    {
        $this->scopeQueriesToCustomer($this->customer->id);

        $this->assertSame(0, DB::table('orders')->update(['updated_at' => now()]));
        $this->assertSame(0, DB::table('order_lines')->delete());
        $this->assertSame(0, DB::table('orders')->delete());

        try {
            DB::transaction(fn (): bool => DB::table('orders')->insert([
                'soda_id' => $this->soda->id,
                'customer_id' => $this->customer->id,
                'pickup_date' => now()->toDateString(),
                'pickup_number' => 99,
            ]));
            $this->fail('A customer inserted an order without the soda of the transaction.');
        } catch (QueryException $exception) {
            $this->assertSame('42501', $exception->getCode());
        }

        $this->assertSame(2, DB::table('orders')->count());
    }

    /** The soda keeps reaching every order of its own, whoever placed it, and none of another soda. */
    public function test_soda_scope_reaches_every_order_of_the_soda(): void
    {
        $this->scopeQueriesTo($this->soda->id);

        $this->assertSame([$this->own->id, $this->foreign->id], DB::table('orders')->orderBy('id')->pluck('id')->all());
    }

    /** A transaction run for a customer, without a soda, reads the orders of that customer and nothing of a soda. */
    public function test_transaction_of_a_customer_reads_their_orders(): void
    {
        $seen = $this->app->make(SodaTransaction::class)->run(null, fn (): array => [
            DB::scalar("select current_setting('app.soda_id', true)"),
            DB::scalar("select current_setting('app.customer_id', true)"),
            OrderModel::query()->orderBy('id')->pluck('id')->all(),
            DB::table('dishes')->count(),
        ], $this->customer->id);

        $this->assertSame(['', $this->customer->id, [$this->own->id, $this->elsewhere->id], 0], $seen);
    }

    /** Create an order of the customer at the soda with one line. */
    private function orderOf(UserModel $customer, SodaModel $soda): OrderModel
    {
        $order = OrderModel::factory()->create(['soda_id' => $soda->id, 'customer_id' => $customer->id]);
        OrderLineModel::factory()->for($order, 'order')->create();

        return $order;
    }
}
