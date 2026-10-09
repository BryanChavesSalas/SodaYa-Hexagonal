<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Sodas\Profile\Domain\Contracts\SodaRepository;
use Src\Sodas\Profile\Infrastructure\Persistence\Repositories\EloquentSodaRepository;

final class ProfileServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SodaRepository::class => EloquentSodaRepository::class,
    ];
}
