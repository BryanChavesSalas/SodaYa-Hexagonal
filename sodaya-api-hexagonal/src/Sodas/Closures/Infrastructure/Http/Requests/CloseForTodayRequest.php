<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CloseForTodayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['nullable', 'string', 'max:200'],
        ];
    }
}
