<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\Contracts\SodaContext;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class AuthenticatedSodaContextTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    /** The soda is the one of the authenticated user and is shared with the row policies. */
    public function test_resolves_the_soda_of_the_authenticated_user(): void
    {
        $user = UserModel::factory()->owner()->create();
        Sanctum::actingAs($user);

        DB::beginTransaction();

        $this->assertSame($user->soda_id, app(SodaContext::class)->current()->value);
        $this->assertSame($user->soda_id, DB::scalar("select current_setting('app.soda_id')"));

        DB::rollBack();
    }

    /** Without a session there is no soda to operate on. */
    public function test_fails_without_an_authenticated_user(): void
    {
        $this->expectException(AuthenticationException::class);

        app(SodaContext::class)->current();
    }

    /** A user without a soda cannot operate on any. */
    public function test_fails_for_a_user_without_soda(): void
    {
        Sanctum::actingAs(UserModel::factory()->create());

        $this->expectException(AuthenticationException::class);

        app(SodaContext::class)->current();
    }

    /** Staff endpoints answer 401 without a token. */
    public function test_staff_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/cocina/platos')->assertUnauthorized();
    }
}
