<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Application\UseCases\PlaceOrder;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\PortionsSoldOutException;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class PlaceOrderTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private SodaModel $soda;

    private UserModel $customer;

    private DishModel $casado;

    private DishModel $fresco;

    /** A soda with 3 casados and 10 frescos left, and a customer. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = UserModel::factory()->create();
        $this->soda = SodaModel::factory()->create();
        $this->casado = DishModel::factory()->create(['soda_id' => $this->soda->id, 'available_portions' => 3, 'price' => 2_800]);
        $this->fresco = DishModel::factory()->create(['soda_id' => $this->soda->id, 'available_portions' => 10, 'price' => 1_000]);
    }

    /** The order and its lines are stored, and the portions come off the menu. */
    public function test_order_is_stored_and_its_portions_taken(): void
    {
        $order = $this->place([$this->casado->id => 2, $this->fresco->id => 1]);

        $this->assertDatabaseHas('orders', ['id' => $order->id->value, 'status' => 'pending', 'customer_id' => $this->customer->id]);
        $this->assertDatabaseCount('order_lines', 2);
        $this->assertSame(1, $this->casado->refresh()->available_portions);
        $this->assertSame(9, $this->fresco->refresh()->available_portions);
    }

    /** Asking for more than what is left stores no order and takes no portion. */
    public function test_short_dish_stores_nothing(): void
    {
        try {
            $this->place([$this->fresco->id => 1, $this->casado->id => 4]);
            $this->fail('An order took more portions than the soda had.');
        } catch (PortionsSoldOutException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(3, $this->casado->refresh()->available_portions);
            $this->assertSame(10, $this->fresco->refresh()->available_portions);
        }
    }

    /** The portions never go below zero: the last ones can be sold, and then nothing else. */
    public function test_portions_never_go_below_zero(): void
    {
        $this->place([$this->casado->id => 3]);

        $this->expectException(PortionsSoldOutException::class);

        try {
            $this->place([$this->casado->id => 1]);
        } finally {
            $this->assertSame(0, $this->casado->refresh()->available_portions);
        }
    }

    /**
     * Place an order of the customer with the quantities of each dish.
     *
     * @param  array<string, int>  $quantities
     */
    private function place(array $quantities): Order
    {
        $this->scopeQueriesTo($this->soda->id);
        $lines = [];

        foreach ($quantities as $dishId => $quantity) {
            $dish = DishModel::query()->findOrFail($dishId);
            $lines[] = ['dish_id' => $dishId, 'dish_name' => $dish->name, 'unit_price' => $dish->price, 'quantity' => $quantity];
        }

        return app(PlaceOrder::class)->execute(
            new PlaceOrderCommand($this->customer->id, $this->soda->id, $lines),
            now()->toDateTimeImmutable(),
        );
    }
}
