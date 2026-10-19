<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\TestCase;

final class ScopeRequestToSodaTest extends TestCase
{
    private const string SODA_ID = '0192f0c4-0000-7000-8000-0000000000aa';

    private const string CUSTOMER_ID = '0192f0c4-0000-7000-8000-0000000000cc';

    /** Register throwaway routes that report the soda and the depth of their transaction. */
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/v1/pruebas')->middleware('api')->group(function (): void {
            Route::get('personal', fn (): array => self::scope())->middleware(['auth:sanctum', 'soda:user']);
            Route::get('sodas/{soda}', fn (): array => self::scope())->middleware('soda:route');
            Route::get('cliente', fn (): array => [
                ...self::scope(),
                'cliente' => DB::scalar("select current_setting('app.customer_id', true)"),
            ])->middleware(['auth:sanctum', 'soda:customer']);
        });
    }

    /** A customer request runs in one transaction with no soda and the authenticated customer, who reads their own orders. */
    public function test_customer_request_is_scoped_to_the_customer_without_soda(): void
    {
        $customer = UserModel::factory()->make(['id' => self::CUSTOMER_ID, 'role' => Role::Customer->value, 'soda_id' => null]);
        Sanctum::actingAs($customer, Role::Customer->abilities());

        $this->getJson('/api/v1/pruebas/cliente')
            ->assertExactJson(['soda' => '', 'cliente' => self::CUSTOMER_ID, 'transacciones' => 1]);
    }

    /** A staff request runs in one transaction scoped to the soda of the authenticated person. */
    public function test_staff_request_is_scoped_to_the_soda_of_the_user(): void
    {
        $this->actAs(Role::Owner, self::SODA_ID);

        $this->getJson('/api/v1/pruebas/personal')->assertExactJson(['soda' => self::SODA_ID, 'transacciones' => 1]);
    }

    /** A person without soda gets the empty value, so no row of a soda is reachable. */
    public function test_person_without_soda_gets_an_empty_scope(): void
    {
        $this->actAs(Role::Customer, null);

        $this->getJson('/api/v1/pruebas/personal')->assertExactJson(['soda' => '', 'transacciones' => 1]);
    }

    /** A public menu request runs in one transaction scoped to the soda of the URL. */
    public function test_menu_request_is_scoped_to_the_soda_of_the_url(): void
    {
        $this->getJson('/api/v1/pruebas/sodas/'.self::SODA_ID)
            ->assertExactJson(['soda' => self::SODA_ID, 'transacciones' => 1]);
    }

    /** The soda dies with the transaction, so the next use of the connection does not inherit it. */
    public function test_scope_ends_with_the_request_transaction(): void
    {
        $this->getJson('/api/v1/pruebas/sodas/'.self::SODA_ID)->assertOk();

        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame('', DB::scalar("select current_setting('app.soda_id', true)"));
    }

    /**
     * Soda of the open transaction and how many transactions are open.
     *
     * @return array{soda: mixed, transacciones: int}
     */
    private static function scope(): array
    {
        return [
            'soda' => DB::scalar("select current_setting('app.soda_id', true)"),
            'transacciones' => DB::transactionLevel(),
        ];
    }

    /** Authenticate an account built in memory, without touching the database. */
    private function actAs(Role $role, ?string $sodaId): void
    {
        Sanctum::actingAs(UserModel::factory()->make(['role' => $role->value, 'soda_id' => $sodaId]), $role->abilities());
    }
}
