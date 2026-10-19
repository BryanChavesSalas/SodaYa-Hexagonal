<?php

declare(strict_types=1);

namespace Src\Loyalty\Points\Infrastructure\Http\Requests;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Foundation\Http\FormRequest;
use Src\Loyalty\Points\Application\DTOs\RedeemPointsCommand;

#[SchemaName('Canje')]
final class RedeemPointsRequest extends FormRequest
{
    /**
     * Validation rules for a redemption.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            /** Puntos que se canjean, un número entero mayor que cero. */
            'puntos' => ['required', 'integer', 'between:1,1000000'],
        ];
    }

    /** Map the validated payload to the use case input, for the authenticated customer. */
    public function toCommand(): RedeemPointsCommand
    {
        return new RedeemPointsCommand((string) $this->user()?->getAuthIdentifier(), $this->integer('puntos'));
    }
}
