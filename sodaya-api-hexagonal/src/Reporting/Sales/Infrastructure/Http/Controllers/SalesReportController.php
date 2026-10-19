<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure\Http\Controllers;

use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Src\Reporting\Sales\Application\UseCases\GetHourlySales;
use Src\Reporting\Sales\Infrastructure\Http\Requests\HourlySalesRequest;
use Src\Reporting\Sales\Infrastructure\Http\Resources\HourlySalesReportResource;
use Src\Shared\Domain\Contracts\SodaContext;

#[Group('Reportes', 'Reportes de ventas de la soda, solo para el dueño.')]
final readonly class SalesReportController
{
    /** Receive the port that identifies the current soda. */
    public function __construct(private SodaContext $sodaContext) {}

    /** Sales of the current soda grouped by hour. */
    #[Endpoint(
        title: 'Consultar las ventas por hora',
        description: 'Cuenta los pedidos entregados de una fecha y suma lo que vendieron, por la hora de Costa Rica en que se crearon. Los totales no incluyen propinas. Solo aparecen las horas con al menos `minimo_pedidos` pedidos.',
    )]
    public function hourly(HourlySalesRequest $request, GetHourlySales $getHourlySales): HourlySalesReportResource
    {
        return new HourlySalesReportResource($getHourlySales->execute(
            $this->sodaContext->current()->value,
            $request->reportDate(),
            $request->minimumOrders(),
        ));
    }
}
