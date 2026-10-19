<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Src\Catalog\Dishes\Infrastructure\Persistence\Models\DishModel;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Ordering\Orders\Infrastructure\Persistence\Models\OrderModel;
use Src\Shared\Infrastructure\Tenancy\ScopeRequestToSoda;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class ScopeRequestToOrderTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string MISSING_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

    private UserModel $customer;

    private OrderModel $own;

    private OrderModel $foreign;

    /** A customer with an order at a soda that has one dish, another customer with an order, and a throwaway route scoped to the order. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = UserModel::factory()->create();
        $this->own = OrderModel::factory()->create(['customer_id' => $this->customer->id]);
        $this->foreign = OrderModel::factory()->create();
        DishModel::factory()->create(['soda_id' => $this->own->soda_id]);

        Sanctum::actingAs($this->customer, Role::Customer->abilities());

        Route::prefix('api/v1/pruebas')->middleware('api')->group(function (): void {
            Route::get('pedidos/{pedido}', fn (Request $request): array => [
                'soda' => DB::scalar("select current_setting('app.soda_id', true)"),
                'cliente' => DB::scalar("select current_setting('app.customer_id', true)"),
                'atributo' => $request->attributes->get(ScopeRequestToSoda::SODA_ATTRIBUTE),
                'platos' => DB::table('dishes')->count(),
                'transacciones' => DB::transactionLevel(),
            ])->middleware(['auth:sanctum', 'soda:order'])->whereUuid('pedido');
        });
    }

    /** The request runs as the customer and on the soda of their order, which is also left on the request; the dishes of that soda are reachable. */
    public function test_request_runs_as_the_customer_on_the_soda_of_the_order(): void
    {
        $this->getJson("/api/v1/pruebas/pedidos/{$this->own->id}")
            ->assertOk()
            ->assertExactJson([
                'soda' => $this->own->soda_id,
                'cliente' => $this->customer->id,
                'atributo' => $this->own->soda_id,
                'platos' => 1,
                'transacciones' => 2,
            ]);
    }

    /** An order of another customer answers the same 404 pedido-no-encontrado as an order that does not exist. */
    public function test_order_of_another_customer_answers_like_a_missing_one(): void
    {
        $foreignResponse = $this->getJson("/api/v1/pruebas/pedidos/{$this->foreign->id}");
        $missingResponse = $this->getJson('/api/v1/pruebas/pedidos/'.self::MISSING_ID);

        $foreignResponse
            ->assertNotFound()
            ->assertJsonPath('type', 'http://localhost/problemas/pedido-no-encontrado')
            ->assertJsonPath('title', 'Pedido no encontrado')
            ->assertJsonPath('detail', 'El pedido no existe.');
        $this->assertSame(
            Arr::except($missingResponse->json(), 'instance'),
            Arr::except($foreignResponse->json(), 'instance'),
        );
    }
}
