<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use Src\Identity\Users\Application\UseCases\RegisterStaffMember;
use Src\Sodas\Profile\Application\UseCases\RegisterSoda;
use Tests\TestCase;

final class ApiRoutingTest extends TestCase
{
    /** The API is served under the versioned prefix. */
    public function test_api_is_served_under_the_v1_prefix(): void
    {
        $this->get('/api/v1')
            ->assertOk()
            ->assertExactJson(['nombre' => 'SodaYa', 'version' => 'v1']);
    }

    /** Unversioned paths are not routed. */
    public function test_unversioned_api_path_is_not_routed(): void
    {
        $this->get('/api')->assertNotFound();
    }

    /** Unknown routes answer a problem document even without an Accept header. */
    public function test_unknown_routes_respond_with_a_problem_document(): void
    {
        $this->get('/api/v1/desconocido')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    /** Sodas and accounts are registered from the console, so no API route creates them. */
    public function test_no_api_route_creates_sodas_or_accounts(): void
    {
        $writes = array_filter(
            Route::getRoutes()->getRoutes(),
            fn (RouteDefinition $route): bool => str_starts_with($route->uri(), 'api/')
                && array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']) !== [],
        );

        $this->assertNotEmpty($writes, 'No writing route was found, so the check proves nothing.');

        $this->assertSame([], $this->describe(array_filter($writes, $this->hasRegistrationAddress(...))));
        $this->assertSame([], $this->describe(array_filter($writes, $this->receivesRegistrationUseCase(...))));
    }

    /** Tell whether the address of the route names a soda, an account or a registration. */
    private function hasRegistrationAddress(RouteDefinition $route): bool
    {
        $uri = mb_strtolower($route->uri());

        return preg_match('#(^|/)sodas(/\{[^}]+\})?$#', $uri) === 1
            || array_any(
                ['usuarios', 'cuenta', 'cuentas', 'registro', 'registrar', 'register', 'signup', 'dueño', 'dueno', 'personal'],
                fn (string $word): bool => str_contains($uri, $word),
            );
    }

    /** Tell whether the constructor or the action of the route receives a registration use case. */
    private function receivesRegistrationUseCase(RouteDefinition $route): bool
    {
        $controller = $route->getControllerClass();

        $functions = $controller === null
            ? [new ReflectionFunction($route->getAction('uses'))]
            : array_filter([
                new ReflectionClass($controller)->getConstructor(),
                new ReflectionMethod($controller, $route->getActionMethod()),
            ]);

        return array_any(
            $functions,
            fn (ReflectionFunctionAbstract $function): bool => array_any(
                $function->getParameters(),
                fn (ReflectionParameter $parameter): bool => in_array(
                    (string) $parameter->getType(),
                    [RegisterSoda::class, RegisterStaffMember::class],
                    true,
                ),
            ),
        );
    }

    /**
     * Describe routes by their methods and address.
     *
     * @param  array<int, RouteDefinition>  $routes
     * @return list<string>
     */
    private function describe(array $routes): array
    {
        return array_values(array_map(
            fn (RouteDefinition $route): string => implode('|', $route->methods()).' '.$route->uri(),
            $routes,
        ));
    }
}
