<?php

declare(strict_types=1);

namespace Src\Sodas\OpeningHours\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\OpeningHours\Application\DTOs\AddTimeSlotCommand;
use Src\Sodas\OpeningHours\Domain\ValueObjects\DayOfWeek;

#[SchemaName('CrearFranja')]
final class StoreTimeSlotRequest extends FormRequest
{
    /**
     * Validation rules for a new slot of the schedule.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'dia' => ['required', 'integer', 'between:'.DayOfWeek::MONDAY.','.DayOfWeek::SUNDAY],
            'abre' => ['required', 'date_format:H:i'],
            'cierra' => ['required', 'date_format:H:i', 'after:abre'],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(SodaContext $sodaContext): AddTimeSlotCommand
    {
        return new AddTimeSlotCommand(
            $sodaContext->current()->value,
            $this->integer('dia'),
            $this->string('abre')->toString(),
            $this->string('cierra')->toString(),
        );
    }
}
