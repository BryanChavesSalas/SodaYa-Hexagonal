<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Reporting\Sales\Application\Contracts\SalesReport;
use Src\Reporting\Sales\Infrastructure\Persistence\Queries\PostgresSalesReport;

final class SalesServiceProvider extends ServiceProvider
{
    /**
     * Ports of the module bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        SalesReport::class => PostgresSalesReport::class,
    ];
}
