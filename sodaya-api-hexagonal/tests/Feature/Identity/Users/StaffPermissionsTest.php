<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class StaffPermissionsTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private SodaModel $soda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->soda = SodaModel::factory()->create();
    }

    /**
     * Each staff endpoint enforces the abilities of the authenticated role.
     *
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('staffEndpoints')]
    public function test_staff_endpoint_permissions(
        string $method,
        string $uri,
        array $payload,
        int $customerStatus,
        int $kitchenStatus,
        int $ownerStatus,
    ): void {
        $this->assertEndpointStatus(
            Role::Customer,
            $method,
            $uri,
            $payload,
            $customerStatus,
        );

        $this->assertEndpointStatus(
            Role::Kitchen,
            $method,
            $uri,
            $payload,
            $kitchenStatus,
        );

        $this->assertEndpointStatus(
            Role::Owner,
            $method,
            $uri,
            $payload,
            $ownerStatus,
        );
    }

    /**
     * Every staff endpoint rejects a request without a token.
     *
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('unauthenticatedStaffEndpoints')]
    public function test_staff_endpoints_without_token_answer_unauthenticated(
        string $method,
        string $uri,
        array $payload,
    ): void {
        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
            'PATCH' => $this->patchJson($uri, $payload),
            'DELETE' => $this->deleteJson($uri),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        $response
            ->assertUnauthorized()
            ->assertJsonPath(
                'type',
                'http://localhost/problemas/no-autenticado',
            );
    }

    /**
     * Staff endpoints and their expected status for customer, kitchen and owner.
     *
     * @return array<string, array{
     *     string,
     *     string,
     *     array<string, mixed>,
     *     int,
     *     int,
     *     int
     * }>
     */
    public static function staffEndpoints(): array
    {
        $missingId = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

        return [
            'list categories' => [
                'GET',
                '/api/v1/cocina/categorias',
                [],
                403,
                200,
                200,
            ],

            'create category' => [
                'POST',
                '/api/v1/cocina/categorias',
                [],
                403,
                403,
                422,
            ],

            'update category' => [
                'PATCH',
                "/api/v1/cocina/categorias/{$missingId}",
                [],
                403,
                403,
                422,
            ],

            'delete category' => [
                'DELETE',
                "/api/v1/cocina/categorias/{$missingId}",
                [],
                403,
                403,
                404,
            ],

            'list closures' => [
                'GET',
                '/api/v1/cocina/cierres',
                [],
                403,
                200,
                200,
            ],

            'create closure' => [
                'POST',
                '/api/v1/cocina/cierres',
                [],
                403,
                403,
                422,
            ],

            'close today' => [
                'POST',
                '/api/v1/cocina/cierres/hoy',
                [],
                403,
                403,
                201,
            ],

            'delete closure' => [
                'DELETE',
                "/api/v1/cocina/cierres/{$missingId}",
                [],
                403,
                403,
                404,
            ],

            'list opening hours' => [
                'GET',
                '/api/v1/cocina/horario',
                [],
                403,
                200,
                200,
            ],

            'create opening hours' => [
                'POST',
                '/api/v1/cocina/horario',
                [],
                403,
                403,
                422,
            ],

            'delete opening hours' => [
                'DELETE',
                "/api/v1/cocina/horario/{$missingId}",
                [],
                403,
                403,
                404,
            ],

            'list dishes' => [
                'GET',
                '/api/v1/cocina/platos',
                [],
                403,
                200,
                200,
            ],

            'create dish' => [
                'POST',
                '/api/v1/cocina/platos',
                [],
                403,
                403,
                422,
            ],

            'update dish' => [
                'PATCH',
                "/api/v1/cocina/platos/{$missingId}",
                [],
                403,
                403,
                404,
            ],
        ];
    }

    /**
     * Every staff endpoint called without authentication.
     *
     * @return array<string, array{
     *     string,
     *     string,
     *     array<string, mixed>
     * }>
     */
    public static function unauthenticatedStaffEndpoints(): array
    {
        $missingId = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d99';

        return [
            'list categories without token' => [
                'GET',
                '/api/v1/cocina/categorias',
                [],
            ],

            'create category without token' => [
                'POST',
                '/api/v1/cocina/categorias',
                [],
            ],

            'update category without token' => [
                'PATCH',
                "/api/v1/cocina/categorias/{$missingId}",
                [],
            ],

            'delete category without token' => [
                'DELETE',
                "/api/v1/cocina/categorias/{$missingId}",
                [],
            ],

            'list closures without token' => [
                'GET',
                '/api/v1/cocina/cierres',
                [],
            ],

            'create closure without token' => [
                'POST',
                '/api/v1/cocina/cierres',
                [],
            ],

            'close today without token' => [
                'POST',
                '/api/v1/cocina/cierres/hoy',
                [],
            ],

            'delete closure without token' => [
                'DELETE',
                "/api/v1/cocina/cierres/{$missingId}",
                [],
            ],

            'list opening hours without token' => [
                'GET',
                '/api/v1/cocina/horario',
                [],
            ],

            'create opening hours without token' => [
                'POST',
                '/api/v1/cocina/horario',
                [],
            ],

            'delete opening hours without token' => [
                'DELETE',
                "/api/v1/cocina/horario/{$missingId}",
                [],
            ],

            'list dishes without token' => [
                'GET',
                '/api/v1/cocina/platos',
                [],
            ],

            'create dish without token' => [
                'POST',
                '/api/v1/cocina/platos',
                [],
            ],

            'update dish without token' => [
                'PATCH',
                "/api/v1/cocina/platos/{$missingId}",
                [],
            ],
        ];
    }

    /**
     * Authenticate with a role and assert the endpoint status.
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertEndpointStatus(
        Role $role,
        string $method,
        string $uri,
        array $payload,
        int $expectedStatus,
    ): void {
        $user = UserModel::factory()->create([
            'role' => $role->value,
            'soda_id' => $role->isStaff() ? $this->soda->id : null,
        ]);

        Sanctum::actingAs(
            $user,
            $role->abilities(),
        );

        $response = match ($method) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $payload),
            'PATCH' => $this->patchJson($uri, $payload),
            'DELETE' => $this->deleteJson($uri),
            default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
        };

        $response->assertStatus($expectedStatus);
    }
}
