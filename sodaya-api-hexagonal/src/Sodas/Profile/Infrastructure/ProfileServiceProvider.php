<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Sodas\Profile\Application\Contracts\OwnerAccounts;
use Src\Sodas\Profile\Domain\Contracts\SodaRepository;
use Src\Sodas\Profile\Infrastructure\Console\RegisterSodaConsoleCommand;
use Src\Sodas\Profile\Infrastructure\Identity\IdentityOwnerAccounts;
use Src\Sodas\Profile\Infrastructure\Persistence\Repositories\EloquentSodaRepository;

final class ProfileServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        OwnerAccounts::class => IdentityOwnerAccounts::class,
        SodaRepository::class => EloquentSodaRepository::class,
    ];

    /** Register the console command of the module. */
    public function boot(): void
    {
        $this->commands([RegisterSodaConsoleCommand::class]);
    }
}
