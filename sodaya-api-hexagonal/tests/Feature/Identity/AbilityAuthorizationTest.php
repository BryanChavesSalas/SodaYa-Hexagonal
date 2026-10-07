<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Src\Identity\Domain\Enums\Ability;
use Src\Identity\Domain\Enums\Role;
use Src\Identity\Infrastructure\Auth\AccessTokenIssuer;
use Src\Identity\Infrastructure\Http\Middleware\RequireAbility;
use Src\Identity\Infrastructure\Persistence\Models\UserModel;
use Tests\TestCase;

final class AbilityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // One probe endpoint per ability, guarded exactly like real endpoints.
        foreach (Ability::cases() as $ability) {
            Route::middleware(['auth:sanctum', RequireAbility::using($ability)])
                ->get('/__probe/'.$ability->name, fn () => response()->json(['ok' => true]));
        }
    }

    /** @return iterable<string, array{Role, Ability, int}> */
    public static function permissionMatrix(): iterable
    {
        yield 'cliente / realizar pedidos' => [Role::Customer, Ability::PlaceOrders, 200];
        yield 'cliente / operar cocina' => [Role::Customer, Ability::OperateKitchen, 403];
        yield 'cliente / administrar' => [Role::Customer, Ability::Administer, 403];

        yield 'cocina / realizar pedidos' => [Role::Kitchen, Ability::PlaceOrders, 403];
        yield 'cocina / operar cocina' => [Role::Kitchen, Ability::OperateKitchen, 200];
        yield 'cocina / administrar' => [Role::Kitchen, Ability::Administer, 403];

        yield 'dueño / realizar pedidos' => [Role::Owner, Ability::PlaceOrders, 403];
        yield 'dueño / operar cocina' => [Role::Owner, Ability::OperateKitchen, 200];
        yield 'dueño / administrar' => [Role::Owner, Ability::Administer, 200];
    }

    /** @return iterable<string, array{Role}> */
    public static function roles(): iterable
    {
        yield 'cliente' => [Role::Customer];
        yield 'cocina' => [Role::Kitchen];
        yield 'dueño' => [Role::Owner];
    }

    /** @return iterable<string, array{string, string, array<string, string>}> */
    public static function kitchenEndpoints(): iterable
    {
        yield 'listar platos' => ['GET', 'catalog.dishes.index', []];
        yield 'crear plato' => ['POST', 'catalog.dishes.store', []];
        yield 'editar plato' => ['PATCH', 'catalog.dishes.update', ['plato' => '0198f5b4-0000-7000-8000-000000000000']];
    }

    #[Test]
    #[DataProvider('permissionMatrix')]
    public function the_token_ability_decides_access(Role $role, Ability $ability, int $expectedStatus): void
    {
        $token = $this->tokenFor($this->userWithRole($role));

        $this->withToken($token)
            ->getJson('/__probe/'.$ability->name)
            ->assertStatus($expectedStatus);
    }

    #[Test]
    public function a_token_without_any_matching_ability_is_forbidden(): void
    {
        $user = $this->userWithRole(Role::Owner);
        $token = $user->createToken('otro', ['otra:cosa'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/__probe/'.Ability::OperateKitchen->name)
            ->assertForbidden();
    }

    #[Test]
    public function a_request_without_token_is_unauthenticated(): void
    {
        $this->getJson('/__probe/'.Ability::OperateKitchen->name)->assertUnauthorized();
    }

    #[Test]
    #[DataProvider('roles')]
    public function issued_tokens_carry_exactly_the_abilities_of_the_role(Role $role): void
    {
        $newToken = app(AccessTokenIssuer::class)->issueFor($this->userWithRole($role));

        $this->assertSame($role->tokenAbilities(), $newToken->accessToken->abilities);
        $this->assertNotContains('*', $newToken->accessToken->abilities);
    }

    /** @param array<string, string> $parameters */
    #[Test]
    #[DataProvider('kitchenEndpoints')]
    public function customers_cannot_reach_the_kitchen_endpoints(string $method, string $routeName, array $parameters): void
    {
        $token = $this->tokenFor($this->userWithRole(Role::Customer));

        $this->withToken($token)
            ->json($method, route($routeName, $parameters))
            ->assertForbidden();
    }

    /** @param array<string, string> $parameters */
    #[Test]
    #[DataProvider('kitchenEndpoints')]
    public function the_kitchen_endpoints_require_authentication(string $method, string $routeName, array $parameters): void
    {
        $this->json($method, route($routeName, $parameters))->assertUnauthorized();
    }

    private function userWithRole(Role $role): UserModel
    {
        $factory = UserModel::factory();
        $sodaId = (string) Str::uuid();

        return match ($role) {
            Role::Customer => $factory->create(),
            Role::Kitchen => $factory->kitchen($sodaId)->create(),
            Role::Owner => $factory->owner($sodaId)->create(),
        };
    }

    private function tokenFor(UserModel $user): string
    {
        return app(AccessTokenIssuer::class)->issueFor($user)->plainTextToken;
    }
}
