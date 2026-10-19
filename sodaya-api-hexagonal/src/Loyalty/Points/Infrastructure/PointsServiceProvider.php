<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Loyalty\Points\Domain\Contracts\PointMovementRepository;
use Src\Loyalty\Points\Infrastructure\Persistence\Repositories\EloquentPointMovementRepository;

final class PointsServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PointMovementRepository::class => EloquentPointMovementRepository::class,
    ];
}
