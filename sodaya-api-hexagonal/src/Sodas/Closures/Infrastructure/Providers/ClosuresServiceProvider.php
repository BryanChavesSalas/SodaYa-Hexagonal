<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Src\Sodas\Closures\Domain\Contracts\ClosureRepositoryContract;
use Src\Sodas\Closures\Infrastructure\Persistence\Repositories\EloquentClosureRepository;

final class ClosuresServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ClosureRepositoryContract::class,
            EloquentClosureRepository::class
        );
    }
}
