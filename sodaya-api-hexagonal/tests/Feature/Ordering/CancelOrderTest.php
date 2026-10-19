<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use RuntimeException;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Ordering\Orders\Application\Contracts\PortionStock;
use Src\Ordering\Orders\Application\DTOs\CancelOrderCommand;
use Src\Ordering\Orders\Application\UseCases\CancelOrder;
use Src\Ordering\Orders\Domain\Entities\OrderLine;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Domain\ValueObjects\SodaId;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToSoda;
use Tests\TestCase;

final class CancelOrderTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToSoda;

    private OrderModel $order;

    private DishModel $casado;

    private DishModel $fresco;

    /** Create a pending order of two casados and a fresco; the dishes have 5 and 500 portions left. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->order = OrderModel::factory()->create();
        $this->casado = DishModel::factory()->create(['soda_id' => $this->order->soda_id, 'available_portions' => 5]);
        $this->fresco = DishModel::factory()->create(['soda_id' => $this->order->soda_id, 'available_portions' => 500]);
        OrderLineModel::factory()->for($this->order, 'order')->create(['dish_id' => $this->casado->id, 'quantity' => 2]);
        OrderLineModel::factory()->for($this->order, 'order')->create(['dish_id' => $this->fresco->id, 'quantity' => 1]);
        $this->scopeQueriesTo($this->order->soda_id);
    }

    /** The cancellation returns the portions to the dishes, never above 500. */
    public function test_cancellation_returns_the_portions_to_the_dishes(): void
    {
        $this->cancel();

        $this->assertSame('cancelled', $this->order->refresh()->status);
        $this->assertSame('kitchen', $this->order->cancellation_origin);
        $this->assertSame(7, $this->casado->refresh()->available_portions);
        $this->assertSame(500, $this->fresco->refresh()->available_portions);
    }

    /** If returning the portions fails, the order is not cancelled either: both happen in one transaction. */
    public function test_cancellation_and_return_happen_in_one_transaction(): void
    {
        $this->app->bind(PortionStock::class, fn (): PortionStock => new class implements PortionStock
        {
            /** @param  list<OrderLine>  $lines */
            public function reserve(SodaId $sodaId, array $lines): bool
            {
                return true;
            }

            /** @param  list<OrderLine>  $lines */
            public function release(SodaId $sodaId, array $lines): void
            {
                throw new RuntimeException('The dishes table is not reachable.');
            }
        });

        try {
            $this->cancel();
            $this->fail('The cancellation did not fail.');
        } catch (RuntimeException) {
            $this->assertSame('pending', $this->order->refresh()->status);
            $this->assertNull($this->order->cancellation_origin);
            $this->assertSame(5, $this->casado->refresh()->available_portions);
        }
    }

    /** A second cancellation does not return the portions twice. */
    public function test_second_cancellation_returns_nothing(): void
    {
        $this->cancel();
        $this->cancel();

        $this->assertSame(7, $this->casado->refresh()->available_portions);
    }

    /** Cancel the order from the kitchen through the use case. */
    private function cancel(): void
    {
        $this->app->make(CancelOrder::class)->execute(
            new CancelOrderCommand($this->order->id, $this->order->soda_id, 'kitchen'),
            now()->toDateTimeImmutable(),
        );
    }
}
