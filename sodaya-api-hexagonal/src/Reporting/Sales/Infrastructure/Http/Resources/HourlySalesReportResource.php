<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Reporting\Sales\Application\DTOs\HourlySales;
use Src\Reporting\Sales\Application\DTOs\HourlySalesReport;

#[SchemaName('VentasPorHora')]
final class HourlySalesReportResource extends JsonResource
{
    /** Wrap the report of a date. */
    public function __construct(private readonly HourlySalesReport $report)
    {
        parent::__construct($report);
    }

    /**
     * Sales of each hour of the date, in Costa Rica time; the totals are in colones and leave the tip out.
     *
     * @return array{fecha: string, minimo_pedidos: int, horas: list<array{hora: int, pedidos: int, total: int}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'fecha' => $this->report->date,
            'minimo_pedidos' => $this->report->minimumOrders,
            'horas' => array_map(fn (HourlySales $hour): array => [
                'hora' => $hour->hour,
                'pedidos' => $hour->orders,
                'total' => $hour->total,
            ], $this->report->hours),
        ];
    }
}
