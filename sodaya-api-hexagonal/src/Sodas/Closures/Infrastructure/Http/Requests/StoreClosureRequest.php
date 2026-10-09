<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Domain\Contracts\SodaContext;
use Src\Sodas\Closures\Application\DTOs\RegisterClosureCommand;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureDate;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

#[SchemaName('RegistrarCierre')]
final class StoreClosureRequest extends FormRequest
{
    /**
     * Validation rules for a closure on a given date.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'fecha' => ['bail', 'required', 'date_format:'.ClosureDate::FORMAT, 'after_or_equal:today'],
            'motivo' => ['nullable', 'string', 'max:'.ClosureReason::MAX_LENGTH],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(SodaContext $sodaContext): RegisterClosureCommand
    {
        return new RegisterClosureCommand(
            $sodaContext->current()->value,
            $this->string('fecha')->toString(),
            $this->input('motivo'),
        );
    }
}
