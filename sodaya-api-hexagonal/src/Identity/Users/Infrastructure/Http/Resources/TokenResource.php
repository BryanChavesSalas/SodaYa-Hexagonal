<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Users\Application\DTOs\IssuedToken;

/**
 * @property-read IssuedToken $resource
 */
#[SchemaName('TokenDeAcceso')]
final class TokenResource extends JsonResource
{
    /** Wrap an issued access token. */
    public function __construct(private readonly IssuedToken $issuedToken)
    {
        parent::__construct($issuedToken);
    }

    /**
     * Expose the access token.
     *
     * @return array{token: string, tipo: string, abilities: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->issuedToken->token,
            'tipo' => 'Bearer',
            'abilities' => $this->issuedToken->abilities,
        ];
    }
}
