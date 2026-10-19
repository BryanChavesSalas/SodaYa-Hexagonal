<?php

declare(strict_types=1);

namespace Src\Sodas\Profile\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Shared\Domain\Contracts\ImageStorage;
use Src\Sodas\Profile\Domain\Entities\Soda;

/**
 * @property-read Soda $resource
 */
#[SchemaName('Soda')]
final class SodaResource extends JsonResource
{
    /** Wrap a soda aggregate. */
    public function __construct(private readonly Soda $soda)
    {
        parent::__construct($soda);
    }

    /**
     * Expose the public profile of the soda.
     *
     * @return array<string, string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->soda->id->value,
            'nombre' => $this->soda->name->value,
            'logo' => $this->soda->logoPath === null ? null : app(ImageStorage::class)->url($this->soda->logoPath),
        ];
    }
}
