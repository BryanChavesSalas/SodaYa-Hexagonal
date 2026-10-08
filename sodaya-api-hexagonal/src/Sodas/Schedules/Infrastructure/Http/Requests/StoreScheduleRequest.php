<?php

declare(strict_types=1);

namespace Src\Sodas\Schedules\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\Schedules\Application\DTOs\CreateScheduleCommand;
use Src\Sodas\Schedules\Domain\ValueObjects\DayOfWeek;

#[SchemaName('CrearHorario')]
final class StoreScheduleRequest extends FormRequest
{
    private const string TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    /**
     * Validation rules for a new slot of the current soda.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'dia_semana' => ['required', 'integer', 'between:'.DayOfWeek::MONDAY.','.DayOfWeek::SUNDAY],
            'apertura' => ['required', 'string', 'regex:'.self::TIME_PATTERN],
            'cierre' => ['required', 'string', 'regex:'.self::TIME_PATTERN],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(SodaContext $sodaContext): CreateScheduleCommand
    {
        return new CreateScheduleCommand(
            $sodaContext->current()->value,
            $this->integer('dia_semana'),
            $this->string('apertura')->toString(),
            $this->string('cierre')->toString(),
        );
    }
}
