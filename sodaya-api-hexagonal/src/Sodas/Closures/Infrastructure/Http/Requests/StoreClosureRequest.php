<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreClosureRequest extends FormRequest
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
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'motivo' => ['nullable', 'string', 'max:200'],
        ];
    }
}
