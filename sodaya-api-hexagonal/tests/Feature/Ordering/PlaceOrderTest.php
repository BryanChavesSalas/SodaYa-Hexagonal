<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Application\DTOs\PlaceOrderCommand;
use Src\Ordering\Orders\Application\UseCases\PlaceOrder;
use Src\Ordering\Orders\Domain\Entities\Order;
use Src\Ordering\Orders\Domain\Exceptions\DishNotOfSodaException;
use Src\Ordering\Orders\Domain\Exceptions\DishUnavailableException;
use Src\Ordering\Orders\Domain\Exceptions\PortionsSoldOutException;
use Src\Ordering\Orders\Domain\Exceptions\SodaClosedException;
use Src\Sodas\OpeningHours\Infrastructure\Persistence\Models\TimeSlotModel;
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

    /** A soda open on Monday from 6:00 to 14:00, with 3 casados and 10 frescos left, and a customer; it is Monday noon. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->customer = UserModel::factory()->create();
        $this->soda = SodaModel::factory()->create();
        TimeSlotModel::factory()->create(['soda_id' => $this->soda->id, 'day_of_week' => 1, 'opens_at' => '06:00', 'closes_at' => '14:00']);
        $this->casado = DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Casado de pollo', 'available_portions' => 3, 'price' => 2_800]);
        $this->fresco = DishModel::factory()->create(['soda_id' => $this->soda->id, 'name' => 'Fresco de cas', 'available_portions' => 10, 'price' => 1_000]);
    }

    /** The order and its lines are stored with the name and price of the catalog, and the portions come off the menu. */
    public function test_order_is_stored_with_the_catalog_prices_and_its_portions_taken(): void
    {
        $order = $this->place([$this->casado->id => 2, $this->fresco->id => 1]);

        $this->assertDatabaseHas('orders', ['id' => $order->id->value, 'status' => 'pending', 'customer_id' => $this->customer->id]);
        $this->assertDatabaseHas('order_lines', ['order_id' => $order->id->value, 'dish_id' => $this->casado->id, 'dish_name' => 'Casado de pollo', 'unit_price' => 2_800, 'quantity' => 2]);
        $this->assertDatabaseCount('order_lines', 2);
        $this->assertSame(6_600, $order->total());
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

    /** Outside the schedule the soda takes no order and keeps its portions. */
    public function test_closed_soda_takes_no_order(): void
    {
        $this->travelTo('2026-10-05 15:00:00');

        try {
            $this->place([$this->casado->id => 1]);
            $this->fail('A closed soda took an order.');
        } catch (SodaClosedException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(3, $this->casado->refresh()->available_portions);
        }
    }

    /** A dish of another soda is refused as if the soda did not have it. */
    public function test_dish_of_another_soda_is_refused(): void
    {
        $foreign = DishModel::factory()->create();

        $this->expectException(DishNotOfSodaException::class);

        $this->place([$this->casado->id => 1, $foreign->id => 1]);
    }

    /** A dish taken off the menu is refused. */
    public function test_inactive_dish_is_refused(): void
    {
        $retired = DishModel::factory()->inactive()->create(['soda_id' => $this->soda->id]);

        $this->expectException(DishUnavailableException::class);

        $this->place([$retired->id => 1]);
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
            $lines[] = ['dish_id' => $dishId, 'quantity' => $quantity];
        }

        return app(PlaceOrder::class)->execute(
            new PlaceOrderCommand($this->customer->id, $this->soda->id, $lines),
            now()->toDateTimeImmutable(),
        );
    }
}
