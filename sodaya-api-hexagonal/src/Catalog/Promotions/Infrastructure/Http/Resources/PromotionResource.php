<?php

declare(strict_types=1);

namespace Src\Catalog\Promotions\Infrastructure\Http\Resources;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Catalog\Promotions\Domain\Entities\Promotion;

/**
 * @property-read Promotion $resource
 */
#[SchemaName('Promocion')]
final class PromotionResource extends JsonResource
{
    /** Wrap a happy hour. */
    public function __construct(private readonly Promotion $promotion)
    {
        parent::__construct($promotion);
    }

    /**
     * Expose the happy hour with the vocabulary of the business.
     *
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->promotion->id->value,
            'dia' => $this->promotion->weekday->value,
            'inicio' => $this->promotion->window->startsAt->value,
            'fin' => $this->promotion->window->endsAt->value,
            'porcentaje' => $this->promotion->percentage->value,
        ];
    }
}
