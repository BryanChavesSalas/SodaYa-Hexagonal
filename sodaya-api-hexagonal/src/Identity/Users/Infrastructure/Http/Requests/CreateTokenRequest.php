<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;

#[SchemaName('CrearToken')]
final class CreateTokenRequest extends FormRequest
{
    /**
     * Validate the credentials and device name.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
            'device_name' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}
