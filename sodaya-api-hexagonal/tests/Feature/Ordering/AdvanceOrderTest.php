<?php

declare(strict_types=1);

namespace Tests\Feature\Ordering;

use Database\Factories\OrderFactory;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderLineModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Tests\Support\Catalog\ActsOnSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class AdvanceOrderTest extends TestCase
{
    use ActsOnSoda, RefreshDatabaseAsOwner;

    private const string MISSING_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    /** Authenticate the kitchen of a soda on a Monday at noon. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 12:00:00');
        $this->actOnNewSoda(Role::Kitchen);
    }

    /** The kitchen moves a paid order through every status, and each answer shows the moment of the step. */
    public function test_kitchen_advances_a_paid_order_step_by_step(): void
    {
        $order = $this->orderWithLine(OrderModel::factory()->paid());

        foreach (['confirmado' => 'confirmado_en', 'listo' => 'listo_en', 'entregado' => 'entregado_en'] as $status => $moment) {
            $this->travel(5)->minutes();

            $this->patchJson($this->endpoint($order->id), ['estado' => $status])
                ->assertOk()
                ->assertJsonPath('data.estado', $status)
                ->assertJsonPath("data.{$moment}", now()->format(DATE_ATOM));
        }

        $this->assertSame('delivered', $order->refresh()->status);
    }

    /** The answer describes the whole order with its lines and its total. */
    public function test_answer_describes_the_order(): void
    {
        $order = $this->orderWithLine(OrderModel::factory()->paid());
        $line = $order->lines()->sole();

        $this->patchJson($this->endpoint($order->id), ['estado' => 'confirmado'])
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.cancelado_por', null)
            ->assertJsonPath('data.pago.estado', 'aprobado')
            ->assertJsonPath('data.lineas.0.plato_id', $line->dish_id)
            ->assertJsonPath('data.lineas.0.cantidad', 2)
            ->assertJsonPath('data.lineas.0.subtotal', $line->unit_price * 2)
            ->assertJsonPath('data.total', $line->unit_price * 2);
    }

    /** A jump or a step back answers 409 transicion-no-permitida and leaves the order as it was. */
    #[DataProvider('refusedSteps')]
    public function test_jump_or_step_back_is_refused(string $state, string $target): void
    {
        $order = $this->orderWithLine(OrderModel::factory()->{$state}());
        $status = $order->status;

        $this->patchJson($this->endpoint($order->id), ['estado' => $target])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/transicion-no-permitida')
            ->assertJsonPath('detail', 'El pedido no puede pasar a ese estado.');

        $this->assertSame($status, $order->refresh()->status);
    }

    /**
     * Factory state of the order and a target the flow does not allow from it.
     *
     * @return array<string, array{string, string}>
     */
    public static function refusedSteps(): array
    {
        return [
            'pending to ready' => ['paid', 'listo'],
            'pending to delivered' => ['paid', 'entregado'],
            'confirmed to confirmed' => ['confirmed', 'confirmado'],
            'ready back to confirmed' => ['ready', 'confirmado'],
            'delivered back to ready' => ['delivered', 'listo'],
            'cancelled to confirmed' => ['cancelled', 'confirmado'],
        ];
    }

    /** Confirming an order whose payment is not approved answers 409 pedido-sin-pago. */
    public function test_unpaid_order_cannot_be_confirmed(): void
    {
        $order = $this->orderWithLine(OrderModel::factory());

        $this->patchJson($this->endpoint($order->id), ['estado' => 'confirmado'])
            ->assertConflict()
            ->assertJsonPath('type', 'http://localhost/problemas/pedido-sin-pago')
            ->assertJsonPath('title', 'Pedido sin pago');

        $this->assertSame('pending', $order->refresh()->status);
    }

    /** An order of another soda answers the same 404 as one that does not exist. */
    public function test_order_of_another_soda_answers_like_a_missing_one(): void
    {
        $foreign = OrderModel::factory()->paid()->create();

        $foreignResponse = $this->patchJson($this->endpoint($foreign->id), ['estado' => 'confirmado'])->assertNotFound();
        $missingResponse = $this->patchJson($this->endpoint(self::MISSING_ID), ['estado' => 'confirmado'])->assertNotFound();

        $this->assertSame(Arr::except($missingResponse->json(), 'instance'), Arr::except($foreignResponse->json(), 'instance'));
        $this->assertSame('http://localhost/problemas/pedido-no-encontrado', $foreignResponse->json('type'));
        $this->assertSame('El pedido no existe.', $foreignResponse->json('detail'));
        $this->scopeQueriesTo($foreign->soda_id);
        $this->assertSame('pending', $foreign->refresh()->status);
    }

    /** Only the three statuses of the kitchen flow are accepted. */
    #[DataProvider('invalidStatuses')]
    public function test_status_outside_the_kitchen_flow_is_rejected(mixed $status): void
    {
        $order = $this->orderWithLine(OrderModel::factory()->paid());

        $this->patchJson($this->endpoint($order->id), ['estado' => $status])
            ->assertUnprocessable()
            ->assertJsonStructure(['errores' => ['estado']]);
    }

    /**
     * Values the endpoint rejects before reaching the domain.
     *
     * @return array<string, array{mixed}>
     */
    public static function invalidStatuses(): array
    {
        return [
            'cancelled' => ['cancelado'],
            'pending' => ['pendiente'],
            'domain value' => ['confirmed'],
            'missing' => [null],
        ];
    }

    /** Create an order of the current soda with one line of two portions. */
    private function orderWithLine(OrderFactory $factory): OrderModel
    {
        $order = $factory->create(['soda_id' => $this->soda->id]);
        OrderLineModel::factory()->for($order, 'order')->create(['quantity' => 2]);

        return $order;
    }

    /** Address of the status of an order. */
    private function endpoint(string $orderId): string
    {
        return "/api/v1/cocina/pedidos/{$orderId}/estado";
    }
}
