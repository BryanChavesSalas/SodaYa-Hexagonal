<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Identity\Users\Application\DTOs\IssueTokenCommand;

#[SchemaName('Credenciales')]
final class IssueTokenRequest extends FormRequest
{
    /**
     * Validate credentials and the device name.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'correo' => [
                'required',
                'string',
                'email',
                'max:255',
            ],
            'contrasena' => [
                'required',
                'string',
            ],
            'dispositivo' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    /** Map the validated payload to the use case input. */
    public function toCommand(): IssueTokenCommand
    {
        return new IssueTokenCommand(
            email: $this->string('correo')->toString(),
            password: $this->string('contrasena')->toString(),
            deviceName: $this->string('dispositivo')->toString(),
        );
    }
}
