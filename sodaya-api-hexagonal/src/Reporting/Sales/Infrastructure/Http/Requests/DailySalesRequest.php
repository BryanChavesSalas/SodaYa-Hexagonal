<?php

declare(strict_types=1);

namespace Src\Reporting\Sales\Infrastructure\Http\Requests;

use Closure;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class DailySalesRequest extends FormRequest
{
    public const int MAX_DAYS = 366;

    private const string DATE_FORMAT = 'Y-m-d';

    /**
     * Validation rules for the range of dates.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Primer día del rango, AAAA-MM-DD, en fecha de Costa Rica. */
            'desde' => ['bail', 'required', 'date_format:'.self::DATE_FORMAT],
            /** Último día del rango, incluido, AAAA-MM-DD. No puede ser anterior a `desde` ni dejar un rango de más de 366 días. */
            'hasta' => ['bail', 'required', 'date_format:'.self::DATE_FORMAT, 'after_or_equal:desde'],
        ];
    }

    /**
     * Once both dates have the right shape, refuse a range longer than a year.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['desde', 'hasta'])) {
                return;
            }

            $days = (new DateTimeImmutable($this->from()))->diff(new DateTimeImmutable($this->to()))->days + 1;

            if ($days > self::MAX_DAYS) {
                $validator->errors()->add('hasta', __('validation.custom.hasta.max_days', ['max' => self::MAX_DAYS]));
            }
        }];
    }

    /** First day of the range. */
    public function from(): string
    {
        return $this->string('desde')->toString();
    }

    /** Last day of the range, included. */
    public function to(): string
    {
        return $this->string('hasta')->toString();
    }
}
