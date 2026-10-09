<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepository;
use Src\Sodas\Closures\Infrastructure\Persistence\Repositories\EloquentClosureRepository;

final class ClosuresServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ClosureRepository::class => EloquentClosureRepository::class,
    ];
}
