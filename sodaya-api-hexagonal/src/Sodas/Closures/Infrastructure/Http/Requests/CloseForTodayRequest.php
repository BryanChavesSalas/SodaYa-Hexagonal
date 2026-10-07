<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Sodas\Closures\Domain\ValueObjects\ClosureReason;

#[SchemaName('CerrarPorHoy')]
final class CloseForTodayRequest extends FormRequest
{
    /**
     * Validation rules for closing the rest of today.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:'.ClosureReason::MAX_LENGTH],
        ];
    }

    /** Optional reason given for the closure. */
    public function reason(): ?string
    {
        return $this->input('motivo');
    }
}
