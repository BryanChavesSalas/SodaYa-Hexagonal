<?php

declare(strict_types=1);

namespace Src\Sodas\Closures\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Sodas\Closures\Domain\Closure;

/** @mixin Closure */
final class ClosureResource extends JsonResource
{
    /**
     * @return array<string, string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id()->value,
            'fecha' => $this->date()->value(),
            'motivo' => $this->reason()->value(),
        ];
    }
}
