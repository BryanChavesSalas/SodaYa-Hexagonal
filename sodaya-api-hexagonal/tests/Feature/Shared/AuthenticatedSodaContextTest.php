<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Shared\Infrastructure\Tenancy\AuthenticatedSodaContext;
use Src\Sodas\Profile\Infrastructure\Persistence\Models\SodaModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class AuthenticatedSodaContextTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** The port is bound to the adapter that reads the authenticated user. */
    public function test_port_is_bound_to_the_authenticated_user_adapter(): void
    {
        $this->assertInstanceOf(AuthenticatedSodaContext::class, $this->app->make(SodaContext::class));
    }

    /** Each staff role works on the soda its account belongs to. */
    #[DataProvider('staffRoles')]
    public function test_staff_member_works_on_the_soda_of_the_account(Role $role): void
    {
        $soda = SodaModel::factory()->create();
        Sanctum::actingAs(UserModel::factory()->create(['role' => $role->value, 'soda_id' => $soda->id]));

        $this->assertSame($soda->id, $this->app->make(SodaContext::class)->current()->value);
    }

    /**
     * Roles that belong to the staff of a soda.
     *
     * @return array<string, array{Role}>
     */
    public static function staffRoles(): array
    {
        return [
            'kitchen' => [Role::Kitchen],
            'owner' => [Role::Owner],
        ];
    }

    /** A customer has no soda, so the staff operations are forbidden. */
    public function test_customer_is_forbidden(): void
    {
        Sanctum::actingAs(UserModel::factory()->create());

        $this->expectException(AuthorizationException::class);

        $this->app->make(SodaContext::class)->current();
    }

    /** Without an authenticated person there is no soda to work on. */
    public function test_guest_is_not_authenticated(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->app->make(SodaContext::class)->current();
    }
}
