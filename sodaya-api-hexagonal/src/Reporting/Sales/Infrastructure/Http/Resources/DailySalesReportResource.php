<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure\Http\Resources;

use DateTimeInterface;
use DateTimeZone;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Reporting\Sales\Application\DTOs\DailySales;
use Src\Reporting\Sales\Application\DTOs\DailySalesReport;

#[SchemaName('VentasDiarias')]
final class DailySalesReportResource extends JsonResource
{
    /** Wrap the report of a range of dates. */
    public function __construct(private readonly DailySalesReport $report)
    {
        parent::__construct($report);
    }

    /**
     * Sales of each day of the range that had delivered orders, in Costa Rica dates; the totals are in colones and
     * leave the tip out. `actualizado_en` is the moment the summary was computed, in ISO 8601, or null if it never was.
     *
     * @return array{desde: string, hasta: string, actualizado_en: string|null, dias: list<array{fecha: string, pedidos: int, total: int}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'desde' => $this->report->from,
            'hasta' => $this->report->to,
            'actualizado_en' => $this->report->refreshedAt
                ?->setTimezone(new DateTimeZone(config()->string('app.timezone')))
                ->format(DateTimeInterface::ATOM),
            'dias' => array_map(fn (DailySales $day): array => [
                'fecha' => $day->date,
                'pedidos' => $day->orders,
                'total' => $day->total,
            ], $this->report->days),
        ];
    }
}
