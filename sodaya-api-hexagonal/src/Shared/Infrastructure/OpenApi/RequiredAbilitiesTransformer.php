<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;

final readonly class RequiredAbilitiesTransformer implements OperationTransformer
{
    /** Copy required Sanctum abilities from the route middleware to OpenAPI. */
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $abilities = [];

        foreach ($routeInfo->route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'abilities:')) {
                continue;
            }

            $required = substr($middleware, strlen('abilities:'));

            foreach (explode(',', $required) as $ability) {
                if ($ability !== '') {
                    $abilities[] = $ability;
                }
            }
        }

        if ($abilities !== []) {
            $operation->setExtensionProperty('abilities', array_values(array_unique($abilities)));
        }
    }
}
