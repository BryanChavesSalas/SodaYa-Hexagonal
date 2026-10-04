<?php

declare(strict_types=1);

namespace Src\Catalog\Menu\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Catalog\Menu\Application\Contracts\PublicMenuReader;
use Src\Catalog\Menu\Infrastructure\Persistence\Queries\EloquentPublicMenuReader;

final class MenuServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        PublicMenuReader::class => EloquentPublicMenuReader::class,
    ];
}
